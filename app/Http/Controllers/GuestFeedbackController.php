<?php

namespace App\Http\Controllers;

use App\Models\Pledge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/** A guest rates the event from the link in their thank-you SMS. Public: the secret link is the key. */
class GuestFeedbackController extends Controller
{
    private function find(string $token): Pledge
    {
        $pledge = Pledge::with('event')->where('invite_token', $token)->first();

        abort_unless($pledge && $pledge->event->hasFeature('cards') && $pledge->event->feedback_enabled, 404);

        return $pledge;
    }

    public function show(string $token): View
    {
        $pledge = $this->find($token);

        return view('guest.feedback', ['pledge' => $pledge, 'event' => $pledge->event, 'open' => $pledge->event->feedbackOpen(), 'done' => session('feedback_done', false)]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $pledge = $this->find($token);
        abort_unless($pledge->event->feedbackOpen(), 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $pledge->update([
            'feedback_rating' => (int) $data['rating'],
            'feedback_message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
            'feedback_at' => now(),
        ]);

        return redirect()->route('guest.feedback', $token)->with('feedback_done', true);
    }
}
