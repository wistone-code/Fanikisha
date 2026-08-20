<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    /** Shown when the logged-in account has no event yet. */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->is_super_user) {
            return redirect()->route('admin.users.index');
        }

        if ($request->user()->currentEvent()) {
            return redirect()->route('dashboard');
        }

        return view('event.create', ['types' => Event::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->is_super_user, 403, "You don't have permission to do that.");
        abort_if($request->user()->currentEvent(), 403, 'Your account is limited to one event.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'in:'.implode(',', Event::TYPES)],
            'place' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'pledge_deadline' => ['required', 'date'],
        ]);

        $event = Event::create($data + ['created_by' => $request->user()->id]);

        // Creating an event automatically makes you its admin.
        EventMember::create([
            'event_id' => $event->id,
            'user_id' => $request->user()->id,
            'role' => 'admin',
        ]);

        return redirect()->route('dashboard')->with('status', 'Event created — you are its admin');
    }

    public function editSettings(): View
    {
        return view('event.settings', ['event' => app('currentEvent'), 'types' => Event::TYPES]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'in:'.implode(',', Event::TYPES)],
            'place' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'pledge_deadline' => ['required', 'date'],
        ]);

        $event->update($data);

        return back()->with('status', 'Event settings saved');
    }

    /** Saves the toggle/frequency/time for automatic recurring reminder broadcasts. */
    public function updateAutoReminder(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'reminder_auto_enabled' => ['nullable', 'boolean'],
            'reminder_auto_frequency_days' => ['required_if:reminder_auto_enabled,1', 'nullable', 'integer', 'min:1', 'max:90'],
            'reminder_auto_time' => ['required_if:reminder_auto_enabled,1', 'nullable', 'date_format:H:i'],
        ]);

        $event->update([
            'reminder_auto_enabled' => (bool) ($data['reminder_auto_enabled'] ?? false),
            'reminder_auto_frequency_days' => $data['reminder_auto_frequency_days'] ?? $event->reminder_auto_frequency_days,
            'reminder_auto_time' => $data['reminder_auto_time'] ?? $event->reminder_auto_time,
        ]);

        return back()->with('status', 'Automatic reminder settings saved');
    }
}
