<?php

namespace App\Http\Controllers;

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
            ->get(['name', 'checked_in_at']);

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

        $wasAlready = $pledge->isCheckedIn();

        if (! $wasAlready) {
            $pledge->update(['checked_in_at' => now()]);
        }

        return response()->json([
            'found' => true,
            'already' => $wasAlready,
            'name' => $pledge->name,
            'checked_in_at' => $pledge->checked_in_at->format('g:i A, M j'),
            'amount' => number_format($pledge->amount),
            'paid' => number_format($pledge->paid),
            'remain' => number_format($pledge->remaining()),
        ]);
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
