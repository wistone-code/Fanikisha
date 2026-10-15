<?php

namespace App\Http\Controllers;

use App\Models\FeedbackItem;
use App\Services\PlainMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Contracts\View\View;

/** Host feedback from inside the app (signed in) and visitor feedback from the public website. */
class FeedbackController extends Controller
{
    public function hostForm(Request $request): View
    {
        return view('feedback.host', ['from' => (string) $request->query('from', '')]);
    }

    public function hostStore(Request $request): RedirectResponse
    {
        $data = Arr::except($this->validated($request), 'rating'); // hosts send no star rating
        $user = $request->user();
        $event = $user->currentEvent();

        $item = FeedbackItem::create($data + [
            'source' => 'host',
            'user_id' => $user->id,
            'event_id' => $event?->id,
            'name' => $user->name,
            'contact' => $user->email ?: $user->phone,
            'page' => mb_substr((string) $request->input('page', ''), 0, 200) ?: null,
        ]);
        $this->notify($item, $event?->name);

        return redirect()->route('feedback.host')->with('status', 'Thank you. Your feedback reached the Fanikisha team.');
    }

    public function siteForm(Request $request): View
    {
        return view('public.feedback', ['lang' => $request->query('lang') === 'sw' ? 'sw' : 'en']);
    }

    public function siteStore(Request $request): RedirectResponse
    {
        $request->validate(['website' => ['nullable', 'max:0']]); // honeypot
        $data = $this->validated($request) + $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:150'],
        ]);

        $item = FeedbackItem::create($data + ['source' => 'website']);
        $this->notify($item, null);

        return redirect()->route('site-feedback', ['lang' => $request->query('lang')])->with('sent', true);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(FeedbackItem::TYPES))],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'message' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
    }

    private function notify(FeedbackItem $item, ?string $eventName): void
    {
        try {
            $who = $item->source === 'host' ? "Host {$item->name}".($eventName ? " ({$eventName})" : '') : 'Website visitor '.($item->name ?: '(no name)');
            $body = "New feedback #{$item->id} — {$item->type}".($item->rating ? ", {$item->rating}/5" : '')."\nFrom: {$who}\nContact: ".($item->contact ?: '—')."\n\n{$item->message}";
            $replyTo = filter_var($item->contact, FILTER_VALIDATE_EMAIL) ?: null;
            app(PlainMailer::class)->send(config('company.email'), "Fanikisha feedback #{$item->id}: {$item->type}", $body, $replyTo, $item->name);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
