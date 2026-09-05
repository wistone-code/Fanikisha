<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventMember;
use App\Services\ActivityLogger;
use App\Services\PhoneNumberService;
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

        ActivityLogger::log('event.created', "{$request->user()->name} created event \"{$event->name}\" ({$event->event_type})", $request->user(), $event);

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

    /** Uploads (or replaces) the photo shown on the guest e-card / invitation. */
    public function uploadCardPhoto(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'card_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $data['card_photo'];

        $event->update([
            'card_photo' => file_get_contents($file->getRealPath()),
            'card_photo_mime' => $file->getMimeType(),
        ]);

        return back()->with('status', 'Card photo updated');
    }

    public function removeCardPhoto(): RedirectResponse
    {
        $event = app('currentEvent');

        $event->update(['card_photo' => null, 'card_photo_mime' => null]);

        return back()->with('status', 'Card photo removed');
    }

    /** Admin-only preview of the current card photo, shown on the settings page. */
    public function viewCardPhoto()
    {
        $event = app('currentEvent');

        abort_unless($event->hasCardPhoto(), 404);

        return response($event->card_photo)->header('Content-Type', $event->card_photo_mime);
    }

    /** Saves the admin's own mobile money number/network so pledgers can pay them directly. */
    public function updatePayout(Request $request, PhoneNumberService $phones): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'payout_phone' => ['nullable', 'string', 'max:32'],
            'payout_network' => ['nullable', 'string', 'in:'.implode(',', array_keys(Event::NETWORK_USSD_CODES))],
        ]);

        $event->update([
            'payout_phone' => $phones->normalize($data['payout_phone'] ?? null),
            'payout_network' => $data['payout_network'] ?? null,
        ]);

        return back()->with('status', 'Payout details saved');
    }

    /**
     * Saves the amount threshold that decides Single vs Double/Couple e-card type.
     * This only affects pledges saved from now on — existing pledges keep
     * whatever card_type they were already assigned.
     */
    public function updateCoupleThreshold(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'couple_threshold_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $event->update([
            'couple_threshold_amount' => $data['couple_threshold_amount'] ?? null,
        ]);

        return back()->with('status', 'Couple threshold saved');
    }

    /**
     * Which language the automated SMS templates use (payment confirmations,
     * reminders, invitations, etc.) — a saved custom message is always sent
     * exactly as written regardless of this setting; it only picks which
     * default text is used when the admin hasn't written their own.
     */
    public function updateSmsLanguage(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'sms_language' => ['required', 'in:en,sw'],
        ]);

        $event->update(['sms_language' => $data['sms_language']]);

        return back()->with('status', 'SMS language saved');
    }

    /** Event admin's own account settings — username. Not available to System Admin (they use /admin/account). */
    public function updateOwnUsername(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$user->id],
        ]);

        $user->update(['username' => $data['username']]);

        return back()->with('status', 'Username updated');
    }

    /** Event admin's own account settings — email. Not available to System Admin (they use /admin/account). */
    public function updateOwnEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update(['email' => $data['email']]);

        return back()->with('status', 'Email updated');
    }

    /**
     * Event admin's own account settings — phone. Needed for SMS-based password
     * recovery to work for their own account. Not available to System Admin.
     */
    public function updateOwnPhone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $request->user()->update(['phone' => $data['phone'] ?? null]);

        return back()->with('status', 'Phone number updated');
    }
}
