<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckinController extends Controller
{
    /** The scanner + manual search page. */
    public function index(): View
    {
        $event = app('currentEvent');

        $checkedInCount = $event->pledges()->whereNotNull('checked_in_at')->count();
        $eligibleCount = $event->pledges()->whereNotNull('invite_token')->count();

        $arrivals = $event->pledges()
            ->whereNotNull('checked_in_at')
            ->orderByDesc('checked_in_at')
            ->get(['id', 'name', 'checked_in_at']);

        return view('event.checkin.index', compact('checkedInCount', 'eligibleCount', 'arrivals'));
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

        $data = $request->validate(['token' => ['required', 'string']]);
        $token = $this->extractToken($data['token']);

        $pledge = $event->pledges()->where('invite_token', $token)->first();

        if (! $pledge) {
            return response()->json(['found' => false], 404);
        }

        // Atomic claim: only the request that actually flips checked_in_at from
        // null wins the "first check-in" outcome. This is correct even if two
        // check-in stations scan the same guest at the exact same instant —
        // the read-then-write version above this comment used to have a race
        // where both requests could read "not checked in yet" and both report
        // success, since the check and the write were two separate steps.
        $now = now();
        $wonCheckin = $event->pledges()
            ->where('id', $pledge->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => $now, 'checked_in_by' => $request->user()->id]) > 0;

        if ($wonCheckin) {
            $pledge->checked_in_at = $now;
            $wasAlready = false;
        } else {
            $pledge->refresh();
            $wasAlready = true;
        }

        return response()->json([
            'found' => true,
            'already' => $wasAlready,
            'id' => $pledge->id,
            'name' => $pledge->name,
            'checked_in_at' => $pledge->checked_in_at->format('g:i A, M j'),
            'amount' => number_format($pledge->amount),
            'paid' => number_format($pledge->paid),
            'remain' => number_format($pledge->remaining()),
        ]);
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
            ->get(['id', 'name', 'phone', 'invite_token', 'card_type', 'checked_in_at'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'phone_last' => $p->phone ? substr(preg_replace('/\D+/', '', $p->phone), -4) : null,
                'token' => $p->invite_token,
                'card_type' => $p->card_type,
                'checked_in_at' => $p->checked_in_at?->format('g:i A, M j'),
            ])->values();

        return response()->json([
            'event_id' => $event->id,
            'generated_at' => now()->toIso8601String(),
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
                $results[] = ['token' => $token, 'status' => 'unknown'];

                continue;
            }

            $when = $this->scanTime($scan['scanned_at'] ?? null);

            $won = $event->pledges()
                ->where('id', $pledge->id)
                ->whereNull('checked_in_at')
                ->update(['checked_in_at' => $when, 'checked_in_by' => $request->user()->id]) > 0;

            if (! $won) {
                $pledge->refresh();
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

        $pledge->update(['checked_in_at' => null]);

        return back()->with('status', 'Check-in removed for '.$pledge->name);
    }

    /** Manual name search fallback, for guests without a smartphone/QR to scan. */
    public function search(Request $request): JsonResponse
    {
        $event = app('currentEvent');

        $data = $request->validate(['q' => ['nullable', 'string', 'max:255']]);

        $pledges = $event->pledges()
            ->whereNotNull('invite_token')
            ->when($data['q'] ?? null, fn ($q) => $q->where('name', 'like', '%'.$data['q'].'%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['invite_token', 'name', 'checked_in_at']);

        return response()->json($pledges->map(fn ($p) => [
            'invite_token' => $p->invite_token,
            'name' => $p->name,
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
