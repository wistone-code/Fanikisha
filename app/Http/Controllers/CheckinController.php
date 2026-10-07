<?php

namespace App\Http\Controllers;

use App\Models\Pledge;
use App\Services\CardCodeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckinController extends Controller
{
    /** The scanner + manual search page. Open to admins and door staff (scanners). */
    public function index(Request $request): View
    {
        $event = app('currentEvent');
        $isAdmin = $request->user()->isAdminOn($event);

        $stats = $this->statsFor($event);
        $checkedInCount = $stats['checked_in'];
        $eligibleCount = $stats['expected'];
        $arrivals = $this->recentArrivals($event, 200);

        return view('event.checkin.index', compact('checkedInCount', 'eligibleCount', 'arrivals', 'isAdmin', 'stats'));
    }

    /** Live numbers for the check-in page (it polls this every 20 seconds). */
    public function stats(): JsonResponse
    {
        return response()->json($this->statsFor(app('currentEvent')) + ['recent' => $this->recentArrivals(app('currentEvent'), 15)->map(fn ($r) => [
            'name' => $r->name, 'time' => $r->checked_in_at->format('g:i A'), 'by' => $r->by_name, 'seat' => $r->seat,
        ])->values()]);
    }

    private function statsFor($event): array
    {
        $cards = $event->pledges()->whereNotNull('invite_token')->get(['id', 'card_type', 'rsvp_status', 'plus_ones', 'checked_in_at', 'checked_in_by']);
        $people = fn ($c) => $c->sum(fn ($p) => $p->headcount());

        $in = $cards->whereNotNull('checked_in_at');
        $perScanner = $in->groupBy('checked_in_by')->map->count();
        $names = DB::table('users')->whereIn('id', $perScanner->keys()->filter())->pluck('name', 'id');

        return [
            'checked_in' => $in->count(),
            'expected' => $cards->count(),
            'people_in' => $in->sum(fn ($p) => 1 + ($p->card_type === 'double' ? 1 : 0) + (int) $p->plus_ones),
            'people_expected' => $people($cards->where('rsvp_status', '!=', 'not_attending')),
            'per_scanner' => $perScanner->map(fn ($n, $id) => ['name' => $id ? ($names[$id] ?? 'Unknown') : 'Unknown', 'count' => $n])->values()->all(),
        ];
    }

    private function recentArrivals($event, int $limit)
    {
        $names = DB::table('users')->pluck('name', 'id');

        return $event->pledges()->with(['seatingTable', 'seatingArea'])
            ->whereNotNull('checked_in_at')->orderByDesc('checked_in_at')->limit($limit)
            ->get()->each(function (Pledge $p) use ($names, $event) {
                $p->by_name = $p->checked_in_by ? ($names[$p->checked_in_by] ?? null) : null;
                $p->seat = $event->seating_published ? $p->seatLabel() : null;
            });
    }

    /** Printable door list: everyone with a card, tick-box, table and card code — a paper backup for the door. */
    public function doorList(): View
    {
        $event = app('currentEvent');

        return view('event.checkin.door-list', [
            'event' => $event,
            'guests' => $event->pledges()->with(['seatingTable', 'seatingArea'])->whereNotNull('invite_token')->orderBy('name')->get(),
        ]);
    }

    /**
     * Called by the scanner page (AJAX) with whatever string was decoded from
     * the QR code — the e-card's QR encodes the guest's public RSVP link, so
     * this accepts either that full URL or a bare token and extracts the token
     * either way. Scoped to $event->pledges() so this can never check in a
     * guest belonging to a different event, even if a token were guessed.
     */
    public function verify(Request $request): JsonResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'preview' => ['nullable', 'boolean'],
        ]);

        $raw = $data['token'] ?? $data['code'] ?? '';
        abort_if($raw === '', 422);

        $pledge = $this->findPledge($event, $raw);

        if (! $pledge) {
            return response()->json(['found' => false, 'revoked' => $this->wasRevoked($event, $raw)], 404);
        }

        // Preview: used when "confirm the guest's name" is on — look up without checking in.
        if ($request->boolean('preview')) {
            return response()->json($this->payload($pledge, $event) + ['found' => true, 'preview' => true, 'already' => $pledge->isCheckedIn()]);
        }

        // Atomic claim: only the request that actually flips checked_in_at from
        // null wins the "first check-in" outcome. This is correct even if two
        // check-in stations scan the same guest at the exact same instant.
        $now = now();
        $wonCheckin = $event->pledges()
            ->where('id', $pledge->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => $now, 'checked_in_by' => $request->user()->id]) > 0;

        if ($wonCheckin) {
            $pledge->checked_in_at = $now;
            $pledge->checked_in_by = $request->user()->id;
            $wasAlready = false;
        } else {
            $pledge->refresh();
            $pledge->increment('scan_attempts');
            $wasAlready = true;
        }

        return response()->json($this->payload($pledge, $event) + ['found' => true, 'already' => $wasAlready]);
    }

    private function findPledge($event, string $raw): ?Pledge
    {
        $trimmed = trim($raw);

        // A short code ("K7M2Q", "k7m-2q") is what a guest reads out when their phone is dead.
        if (! str_contains($trimmed, '/') && strlen(preg_replace('/[^A-Za-z0-9]/', '', $trimmed)) <= 8) {
            $byCode = $event->pledges()->whereNotNull('invite_token')->where('card_code', CardCodeService::normalize($trimmed))->first();

            if ($byCode) {
                return $byCode;
            }
        }

        return $event->pledges()->where('invite_token', $this->extractToken($trimmed))->first();
    }

    private function wasRevoked($event, string $raw): bool
    {
        return DB::table('card_revocations')->where('event_id', $event->id)->where('token_hash', hash('sha256', $this->extractToken($raw)))->exists();
    }

    /** What the door screen shows for a guest. */
    private function payload(Pledge $pledge, $event): array
    {
        $pledge->loadMissing(['seatingTable', 'seatingArea']);

        $by = $pledge->checked_in_by ? DB::table('users')->where('id', $pledge->checked_in_by)->value('name') : null;

        return [
            'id' => $pledge->id,
            'token' => $pledge->invite_token,
            'name' => $pledge->name,
            'checked_in_at' => $pledge->checked_in_at?->format('g:i A, M j'),
            'checked_in_by' => $by,
            'seat' => $event->seating_published ? $pledge->seatLabel() : null,
            'people' => $pledge->headcount() ?: 1,
            'declined' => $pledge->rsvp_status === 'not_attending',
            'group' => $pledge->group_name,
            'meal' => $pledge->meal_choice,
        ];
    }

    /**
     * Compact guest list for the offline scanner: only what is needed to recognise a card
     * and show the result screen. Only guests whose card link is active can be checked in,
     * so only those are included. Also returns a fresh CSRF token for the later sync.
     */
    public function guestList(): JsonResponse
    {
        $event = app('currentEvent');

        $guests = $event->pledges()
            ->whereNotNull('invite_token')
            ->orderBy('name')
            ->with(['seatingTable', 'seatingArea'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->card_code,
                'seat' => $event->seating_published ? $p->seatLabel() : null,
                'people' => $p->headcount() ?: 1,
                'phone_last' => $p->phone ? substr(preg_replace('/\D+/', '', $p->phone), -4) : null,
                'token' => $p->invite_token,
                'card_type' => $p->card_type,
                'checked_in_at' => $p->checked_in_at?->format('g:i A, M j'),
            ])->values();

        return response()->json([
            'event_id' => $event->id,
            'generated_at' => now()->toIso8601String(),
            'confirm_name' => (bool) $event->checkin_confirm_name,
            'csrf' => csrf_token(),
            'guests' => $guests,
        ]);
    }

    /** A fresh CSRF token for a still-logged-in session, used when queued scans are synced hours later. */
    public function freshToken(): JsonResponse
    {
        return response()->json(['csrf' => csrf_token()]);
    }

    /**
     * Applies check-ins that were scanned while offline. Each one is claimed with the same
     * atomic "only if still null" update used for live scans, so the first check-in wins even
     * if another phone scanned the same guest in the meantime. Returns one result per scan:
     * checked_in, already (someone got there first — with their time), or unknown.
     */
    public function sync(Request $request): JsonResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'scans' => ['required', 'array', 'min:1', 'max:500'],
            'scans.*.token' => ['required', 'string', 'max:255'],
            'scans.*.scanned_at' => ['nullable', 'string', 'max:64'],
        ]);

        $results = [];
        $seen = [];

        foreach ($data['scans'] as $scan) {
            $token = $this->extractToken($scan['token']);

            // The same token twice in one batch: only the first counts.
            if (isset($seen[$token])) {
                continue;
            }
            $seen[$token] = true;

            $pledge = $event->pledges()->where('invite_token', $token)->first();

            if (! $pledge) {
                $results[] = ['token' => $token, 'status' => 'unknown', 'revoked' => $this->wasRevoked($event, $token)];

                continue;
            }

            $when = $this->scanTime($scan['scanned_at'] ?? null);

            $won = $event->pledges()
                ->where('id', $pledge->id)
                ->whereNull('checked_in_at')
                ->update(['checked_in_at' => $when, 'checked_in_by' => $request->user()->id]) > 0;

            if (! $won) {
                $pledge->refresh();
                $pledge->increment('scan_attempts');
            } else {
                $pledge->checked_in_at = $when;
            }

            $results[] = [
                'token' => $token,
                'status' => $won ? 'checked_in' : 'already',
                'id' => $pledge->id,
                'name' => $pledge->name,
                'checked_in_at' => $pledge->checked_in_at?->format('g:i A, M j'),
            ];
        }

        return response()->json(['results' => $results]);
    }

    /** Uses the phone's own scan time, but never a future time or an unreadable value. */
    private function scanTime(?string $raw): Carbon
    {
        try {
            $time = $raw ? Carbon::parse($raw) : now();
        } catch (\Throwable $e) {
            return now();
        }

        // Stored times are in the app's own timezone, whatever timezone the phone's ISO string used.
        $time = $time->setTimezone(config('app.timezone'));

        return $time->isFuture() ? now() : $time;
    }

    /** Undo an accidental or mistaken check-in, so the guest shows as not-yet-arrived again. */
    public function undoCheckin(\App\Models\Pledge $pledge): \Illuminate\Http\RedirectResponse
    {
        $event = app('currentEvent');
        abort_unless($pledge->event_id === $event->id, 404);

        $pledge->update(['checked_in_at' => null, 'checked_in_by' => null, 'scan_attempts' => 0]);

        return back()->with('status', 'Check-in removed for '.$pledge->name);
    }

    /** Manual lookup by name, card code or the last digits of a phone number. */
    public function search(Request $request): JsonResponse
    {
        $event = app('currentEvent');

        $data = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $q = trim($data['q'] ?? '');
        $code = CardCodeService::normalize($q);
        $digits = preg_replace('/\D+/', '', $q) ?? '';

        $pledges = $event->pledges()
            ->with(['seatingTable', 'seatingArea'])
            ->whereNotNull('invite_token')
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q, $code, $digits) {
                $w->where('name', 'like', '%'.$q.'%');
                if (strlen($code) >= 4) {
                    $w->orWhere('card_code', $code);
                }
                if (strlen($digits) >= 4) {
                    // A local number typed as 0712 345 678 is stored as +255712345678, so match without the leading 0 as well.
                    $w->orWhere('phone', 'like', '%'.$digits)->orWhere('phone', 'like', '%'.ltrim($digits, '0'));
                }
            }))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($pledges->map(fn ($p) => [
            'invite_token' => $p->invite_token,
            'name' => $p->name,
            'code' => $p->card_code,
            'seat' => $event->seating_published ? $p->seatLabel() : null,
            'checked_in' => $p->isCheckedIn(),
            'checked_in_at' => $p->checked_in_at?->format('g:i A, M j'),
        ]));
    }

    /** If a full URL was scanned, the token is the last path segment. */
    private function extractToken(string $raw): string
    {
        $trimmed = rtrim(trim($raw), '/');
        $parts = explode('/', $trimmed);

        return end($parts);
    }
}
