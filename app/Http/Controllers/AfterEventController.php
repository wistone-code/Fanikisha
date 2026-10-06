<?php

namespace App\Http\Controllers;

use App\Services\ThankYouService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Thank-you messages and the post-event recap. */
class AfterEventController extends Controller
{
    public function index(ThankYouService $thanks): View
    {
        $event = app('currentEvent');
        $cards = $event->pledges()->get();

        return view('event.after.index', [
            'event' => $event,
            'attendedCount' => $thanks->targets($event)->whereNotNull('checked_in_at')->count(),
            'absentCount' => $thanks->targets($event)->whereNull('checked_in_at')->count(),
            'alreadySent' => $cards->whereNotNull('thank_you_sent_at')->count(),
            'eventOver' => $event->event_date->lte(now()->startOfDay()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host_names' => ['nullable', 'string', 'max:160'],
            'thank_you_time' => ['required', 'date_format:H:i'],
            'thank_you_attended_message' => ['nullable', 'string', 'max:1000'],
            'thank_you_absent_message' => ['nullable', 'string', 'max:1000'],
        ]);

        app('currentEvent')->update($data + [
            'thank_you_enabled' => $request->boolean('thank_you_enabled'),
            'thank_you_acknowledge_paid' => $request->boolean('thank_you_acknowledge_paid'),
        ]);

        return back()->with('status', 'Thank-you settings saved');
    }

    public function sendNow(ThankYouService $thanks): RedirectResponse
    {
        $event = app('currentEvent');

        if ($event->event_date->gt(now()->startOfDay())) {
            return back()->with('error', 'Thank-you messages can be sent once the event has started.');
        }

        $result = $thanks->send($event);

        if ($result === null) {
            return back()->with('status', 'Everyone with a phone number has already been thanked.');
        }

        return back()->with($result['successful'] ? 'status' : 'error', $result['successful']
            ? "Thank-you sent to {$result['sent']} people".($result['failed'] ? ", {$result['failed']} failed" : '').'.'
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }

    /** Printable summary of how the event went. */
    public function recap(): View
    {
        $event = app('currentEvent');
        $guests = $event->pledges()->with(['seatingTable', 'seatingArea'])->get();
        $cards = $guests->whereNotNull('invite_token');
        $attendingCards = $cards->where('rsvp_status', 'attending');
        $arrived = $cards->whereNotNull('checked_in_at');

        $people = fn ($c) => $c->sum(fn ($p) => 1 + ($p->card_type === 'double' ? 1 : 0) + (int) $p->plus_ones);

        $byHour = $arrived->groupBy(fn ($p) => $p->checked_in_at->format('H:00'))->map->count()->sortKeys();
        $meals = $attendingCards->whereNotNull('meal_choice')->groupBy('meal_choice')->map->count()->sortDesc();

        return view('event.after.recap', [
            'event' => $event,
            'stats' => [
                'cards' => $cards->count(),
                'sent' => $cards->whereNotNull('invite_sent_at')->count(),
                'opened' => $cards->whereNotNull('first_opened_at')->count(),
                'responded' => $cards->whereNotNull('rsvp_status')->count(),
                'attending' => $attendingCards->count(),
                'declined' => $cards->where('rsvp_status', 'not_attending')->count(),
                'no_reply' => $cards->whereNull('rsvp_status')->count(),
                'expected_people' => $people($attendingCards),
                'arrived' => $arrived->count(),
                'arrived_people' => $people($arrived),
                'no_show' => $attendingCards->whereNull('checked_in_at')->count(),
                'walk_in' => $arrived->where('rsvp_status', '!=', 'attending')->count(),
                'plus_ones' => (int) $attendingCards->sum('plus_ones'),
                'thanked' => $guests->whereNotNull('thank_you_sent_at')->count(),
            ],
            'byHour' => $byHour,
            'meals' => $meals,
            'noShows' => $attendingCards->whereNull('checked_in_at')->sortBy('name')->values(),
            'photos' => $event->photos()->where('hidden', false)->count(),
            'smsUsed' => $event->sms_sent_count,
            'money' => $event->isEcard() ? null : $event->stats(),
        ]);
    }
}
