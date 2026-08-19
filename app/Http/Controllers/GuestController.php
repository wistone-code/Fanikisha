<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Pledge;
use App\Services\MessageTemplateService;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GuestController extends Controller
{
    use AuthorizesEventOwnership;

    public function index(Request $request): View
    {
        $event = app('currentEvent');
        $isAdmin = $request->user()->isAdminOn($event);

        if ($event->isFuneral()) {
            return view('event.guests.announcement', compact('event', 'isAdmin'));
        }

        $tab = $request->get('tab', 'event');

        if ($tab === 'meeting') {
            return view('event.guests.meeting', [
                'event' => $event,
                'pledges' => $event->pledges,
                'isAdmin' => $isAdmin,
            ]);
        }

        $pledges = $isAdmin ? $event->pledges : $event->pledges()->whereColumn('paid', '>=', 'amount')->where('amount', '>', 0)->get();

        return view('event.guests.event-invitation', compact('event', 'pledges', 'isAdmin'));
    }

    /** Activates a pledge's RSVP link. Only possible once it's paid in full — a write action, so admin-only. */
    public function sendInvite(Pledge $pledge): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        abort_unless($pledge->isPaidInFull(), 403, 'This pledge must be paid in full first.');

        if (! $pledge->invite_token) {
            $pledge->update(['invite_token' => Str::random(32)]);
        }

        return back()->with('status', 'Invitation link activated');
    }

    public function inviteSms(Pledge $pledge, MessageTemplateService $messages): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        abort_unless($pledge->invite_token, 403, 'This invitation has not been activated yet.');

        $event = app('currentEvent');
        $body = rawurlencode($messages->forInvitation($event, $pledge));

        return redirect()->away('sms:'.rawurlencode($pledge->phone ?? '')."?body={$body}");
    }

    public function inviteWhatsApp(Pledge $pledge, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        abort_unless($pledge->invite_token, 403, 'This invitation has not been activated yet.');

        $event = app('currentEvent');
        $digits = $phones->digitsOnly($pledge->phone);
        $text = rawurlencode($messages->forInvitation($event, $pledge));

        return redirect()->away("https://wa.me/{$digits}?text={$text}");
    }

    public function updateInvitationMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['invitation_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['invitation_message' => $data['invitation_message']]);

        return back()->with('status', 'Invitation message saved');
    }

    // ---- Meeting invitation (non-Funeral only) --------------------------------------

    public function meetingSms(Pledge $pledge, MessageTemplateService $messages): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $event = app('currentEvent');
        $body = rawurlencode($messages->forMeeting($event, $pledge));

        return redirect()->away('sms:'.rawurlencode($pledge->phone ?? '')."?body={$body}");
    }

    public function meetingWhatsApp(Pledge $pledge, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $event = app('currentEvent');
        $digits = $phones->digitsOnly($pledge->phone);
        $text = rawurlencode($messages->forMeeting($event, $pledge));

        return redirect()->away("https://wa.me/{$digits}?text={$text}");
    }

    public function updateMeetingMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['meeting_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['meeting_message' => $data['meeting_message']]);

        return back()->with('status', 'Meeting message saved');
    }

    // ---- Announcement (Funeral only) -------------------------------------------------

    public function updateAnnouncementMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['announcement_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['announcement_message' => $data['announcement_message']]);

        return back()->with('status', 'Announcement message saved');
    }

    /**
     * Broadcast SMS for Funeral events. The Contact Picker API (real phone-book access)
     * is invoked client-side in event/guests/announcement.blade.php, since it's a
     * browser API with no server equivalent — the picked numbers are POSTed here.
     * Falls back to every saved contact with a phone number when the picker isn't
     * available (e.g. iOS Safari doesn't support it at all) or the person cancels it.
     */
    public function broadcastSms(Request $request, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $event = app('currentEvent');

        // The phone-book numbers are entirely client-supplied (from the browser's
        // Contact Picker), so they're validated as plain short strings and then
        // normalized/filtered exactly like any other phone input before ever
        // reaching the sms: URI we build below.
        $validated = $request->validate([
            'phones' => ['array', 'max:500'],
            'phones.*' => ['string', 'max:32'],
        ]);

        $picked = collect($validated['phones'] ?? [])
            ->map(fn ($p) => $phones->normalize($p))
            ->filter();

        $numbers = $picked->isNotEmpty()
            ? $picked
            : $event->pledges()->whereNotNull('phone')->pluck('phone');

        if ($numbers->isEmpty()) {
            return back()->withErrors(['phones' => 'No contacts available to message.']);
        }

        $body = rawurlencode($messages->forAnnouncement($event, null));

        return redirect()->away('sms:'.rawurlencode($numbers->implode(','))."?body={$body}");
    }
}
