<?php

namespace App\Http\Controllers;

use App\Models\Pledge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/** A guest rates the event from the link in their thank-you SMS. Public: the secret link is the key. */
class GuestFeedbackController extends Controller
{
    private function find(string $code): Pledge
    {
        $pledge = Pledge::with('event')->where('feedback_code', $code)->whereNotNull('invite_token')->first();

        abort_unless($pledge && $pledge->event->hasFeature('cards') && $pledge->event->feedback_enabled, 404);

        return $pledge;
    }

    public function show(string $code): View
    {
        $pledge = $this->find($code);

        return view('guest.feedback', ['pledge' => $pledge, 'event' => $pledge->event, 'open' => $pledge->event->feedbackOpen(), 'done' => session('feedback_done', false)]);
    }

    public function store(Request $request, string $code): RedirectResponse
    {
        $pledge = $this->find($code);
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

        return redirect()->route('guest.feedback', $code)->with('feedback_done', true);
    }
}
