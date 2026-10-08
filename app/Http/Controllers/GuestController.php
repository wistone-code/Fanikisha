<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Pledge;
use App\Services\BeemSmsService;
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

        if ($event->isEcard()) {
            return $this->ecardIndex($request, $event, $isAdmin);
        }

        if ($event->isFuneral()) {
            return view('event.guests.announcement', compact('event', 'isAdmin'));
        }

        $tab = $request->get('tab', 'event');

        if ($tab === 'meeting') {
            return view('event.guests.meeting', [
                'event' => $event,
                'isAdmin' => $isAdmin,
            ]);
        }

        if ($tab === 'rsvp') {
            abort_unless($event->hasFeature('cards'), 403, 'RSVP is not part of your package.');
            $invited = $event->pledges()->whereNotNull('invite_token')->get();

            return view('event.guests.rsvp-status', [
                'event' => $event,
                'invited' => $invited,
                'isAdmin' => $isAdmin,
            ]);
        }

        // Every pledger is on the invitation list automatically, plus any guest added by hand.
        $pledges = $event->pledges()->orderBy('name')->get();

        return view('event.guests.event-invitation', compact('event', 'pledges', 'isAdmin'));
    }

    /** A card can be sent as soon as its link exists — payment never gates an invitation. The SMS package sends plain text, so no link is needed. */
    private function canSendInvite(Pledge $pledge): bool
    {
        return ! $pledge->event->hasFeature('cards') || (bool) $pledge->invite_token;
    }

    // ---- E-card-only accounts ---------------------------------------------------------

    private function ecardIndex(Request $request, $event, bool $isAdmin): View
    {
        $tab = $request->get('tab', 'guests');

        if ($tab === 'rsvp') {
            return view('event.guests.rsvp-status', [
                'event' => $event,
                'invited' => $event->pledges()->whereNotNull('invite_token')->orderBy('name')->get(),
                'isAdmin' => $isAdmin,
            ]);
        }

        return view('event.guests.ecard', [
            'event' => $event,
            'guests' => $event->pledges()->orderBy('name')->get(),
            'isAdmin' => $isAdmin,
        ]);
    }

    /** Which extra questions the RSVP asks (plus-ones, meal, dietary, message) and the reply deadline. */
    public function updateRsvpSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rsvp_max_plus_single' => ['required', 'integer', 'min:0', 'max:10'],
            'rsvp_max_plus_double' => ['required', 'integer', 'min:0', 'max:10'],
            'rsvp_meal_options' => ['nullable', 'string', 'max:1000'],
            'rsvp_cutoff_date' => ['nullable', 'date'],
        ]);

        $options = collect(preg_split('/\r\n|\r|\n/', (string) ($data['rsvp_meal_options'] ?? '')))->map(fn ($o) => trim($o))->filter()->take(10)->implode("\n");

        app('currentEvent')->update([
            'rsvp_plus_ones_enabled' => $request->boolean('rsvp_plus_ones_enabled'),
            'rsvp_max_plus_single' => $data['rsvp_max_plus_single'],
            'rsvp_max_plus_double' => $data['rsvp_max_plus_double'],
            'rsvp_meal_enabled' => $request->boolean('rsvp_meal_enabled'),
            'rsvp_meal_options' => $options ?: null,
            'rsvp_dietary_enabled' => $request->boolean('rsvp_dietary_enabled'),
            'rsvp_message_enabled' => $request->boolean('rsvp_message_enabled'),
            'rsvp_cutoff_date' => $data['rsvp_cutoff_date'] ?? null,
        ]);

        return back()->with('status', 'RSVP options saved');
    }

    private function abortUnlessEcard(): void
    {
        abort_unless(app('currentEvent')->isEcard(), 404);
    }

    private function newEcardGuest($event, string $name, ?string $phone, string $cardType, PhoneNumberService $phones): Pledge
    {
        return $event->pledges()->create([
            'name' => $name,
            'phone' => $phones->normalize($phone ?: null),
            'amount' => 0,
            'paid' => 0,
            'pay_token' => Str::random(32),
            // The card link is live immediately — there is no payment step in e-card mode.
            'invite_token' => Str::random(32),
            'card_type' => $cardType,
        ]);
    }

    public function storeGuest(Request $request, PhoneNumberService $phones): RedirectResponse
    {
        $this->abortUnlessEcard();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'card_type' => ['required', 'in:single,double'],
        ]);

        $this->newEcardGuest(app('currentEvent'), $data['name'], $data['phone'] ?? null, $data['card_type'], $phones);

        return back()->with('status', "{$data['name']} added — their e-card is ready to send");
    }

    private function abortUnlessInvitationList(): void
    {
        $event = app('currentEvent');
        abort_if($event->isEcard() || $event->isFuneral(), 404);
    }

    /** Contribution accounts: invite someone who is not a contributor. Their link is live straight away. */
    public function inviteNewGuest(Request $request, PhoneNumberService $phones): RedirectResponse
    {
        $this->abortUnlessInvitationList();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'card_type' => ['nullable', 'in:single,double'],
        ]);

        $event = app('currentEvent');
        $guest = $this->newEcardGuest($event, $data['name'], $data['phone'] ?? null, $data['card_type'] ?? 'single', $phones);
        $guest->forceFill(['guest_only' => true])->save();

        return back()->with('status', "{$data['name']} added to the invitation list.");
    }

    /** Bulk add from a CSV/text file or pasted rows. Columns: Name, Phone, Card (single/double, optional). */
    public function importGuests(Request $request, PhoneNumberService $phones): RedirectResponse
    {
        $this->abortUnlessEcard();

        $request->validate([
            'import_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
            'import_text' => ['nullable', 'string', 'max:100000'],
        ]);

        $rows = collect();

        if ($request->hasFile('import_file')) {
            $handle = fopen($request->file('import_file')->getRealPath(), 'r');

            while (($line = fgetcsv($handle)) !== false) {
                $rows->push(\App\Support\ImportRows::stripBom($line));
            }

            fclose($handle);
        } elseif ($request->filled('import_text')) {
            $rows = collect(preg_split('/\r\n|\r|\n/', trim($request->input('import_text'))))
                ->filter(fn ($line) => trim($line) !== '')
                ->map(fn ($line) => \App\Support\ImportRows::splitLine($line));
        }

        if ($rows->isEmpty()) {
            return back()->withErrors(['import_file' => 'Upload a file or paste some rows first.']);
        }

        if ($rows->count() > 500) {
            return back()->withErrors(['import_file' => 'Please import up to 500 guests at a time.']);
        }

        $event = app('currentEvent');
        $imported = 0;
        $skipped = 0;
        $seen = $event->pledges()->get(['name', 'phone'])->map(fn ($p) => mb_strtolower(trim($p->name)).'|'.$p->phone)->flip();

        foreach ($rows as $row) {
            $name = trim((string) ($row[0] ?? ''));
            $phone = trim((string) ($row[1] ?? ''));
            $card = strtolower(trim((string) ($row[2] ?? ''))) === 'double' ? 'double' : 'single';

            // A header row such as "Name, Phone" is skipped like any other row without a usable name.
            if ($name === '' || strtolower($name) === 'name') {
                $skipped++;

                continue;
            }

            // The same file uploaded twice must not double the guest list.
            $key = mb_strtolower($name).'|'.$phones->normalize($phone ?: null);
            if ($seen->has($key)) {
                $skipped++;

                continue;
            }
            $seen[$key] = true;

            $this->newEcardGuest($event, $name, $phone, $card, $phones);
            $imported++;
        }

        return back()->with('status', "Added {$imported} guest(s)".($skipped > 0 ? ", skipped {$skipped} row(s) without a name or already on the list" : '').'.');
    }

    public function updateGuest(Request $request, Pledge $pledge, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        // E-card accounts edit any guest; contribution accounts edit only invited guests (a pledger is edited on the pledges page).
        abort_unless(app('currentEvent')->isEcard() || $pledge->guest_only, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'card_type' => ['required', 'in:single,double'],
        ]);

        $pledge->update([
            'name' => $data['name'],
            'phone' => $phones->normalize($data['phone'] ?? null),
            'card_type' => $data['card_type'],
        ]);

        return back()->with('status', 'Guest updated');
    }

    public function destroyGuest(Pledge $pledge): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        // E-card accounts remove any guest; contribution accounts only remove invited guests, never a pledger.
        abort_unless(app('currentEvent')->isEcard() || $pledge->guest_only, 404);

        $pledge->delete();

        return back()->with('status', 'Guest removed');
    }

    /** Activates a pledger's RSVP link — no payment needed. A write action, so admin-only. */
    public function sendInvite(Pledge $pledge): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        if (! $pledge->invite_token) {
            $pledge->update(['invite_token' => Str::random(32)]);
        }

        return back()->with('status', 'Invitation link activated');
    }

    /** Sends the invitation directly via Beem SMS instead of opening the phone's Messages app. */
    public function inviteSms(Pledge $pledge, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        abort_unless($this->canSendInvite($pledge), 403, 'Activate the invitation link first.');

        $event = app('currentEvent');
        $result = $sms->sendSingle($messages->forInvitationSms($event, $pledge), $pledge->phone);

        if ($result['successful']) {
            $pledge->update(['invite_sent_at' => now(), 'invite_channel' => 'sms']);
        }

        return back()->with('status', $result['successful']
            ? "Invitation sent to {$pledge->name}."
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }

    public function inviteWhatsApp(Pledge $pledge, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        abort_unless(app('currentEvent')->hasFeature('cards'), 404);
        $this->assertPledgeInCurrentEvent($pledge);
        if ($blocked = app(\App\Services\OptOutService::class)->blockedRedirect($pledge->phone)) {
            return $blocked;
        }
        abort_unless($this->canSendInvite($pledge), 403, 'Activate the invitation link first.');

        $event = app('currentEvent');
        $digits = $phones->digitsOnly($pledge->phone);
        $text = rawurlencode($messages->forInvitation($event, $pledge));

        // Opening WhatsApp counts as sent (we cannot see whether the host presses send there).
        $pledge->update(['invite_sent_at' => $pledge->invite_sent_at ?? now(), 'invite_channel' => $pledge->invite_channel ?? 'whatsapp']);

        return redirect()->away("https://wa.me/{$digits}?text={$text}");
    }

    public function updateInvitationMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['invitation_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['invitation_message' => $data['invitation_message']]);

        return back()->with('status', 'Invitation message saved');
    }

    // ---- Meeting invitation (non-Funeral only) --------------------------------------

    /** Sends the meeting invitation directly via Beem SMS to every pledger with a phone number. */
    public function meetingBroadcastSms(MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $event = app('currentEvent');
        $message = $messages->forMeeting($event);

        if (trim($message) === '') {
            return back()->withErrors(['meeting_message' => 'Write a meeting message first, then save it before broadcasting.']);
        }

        // A meeting is for the people who contribute, not for guests invited to the event.
        $pledgers = $event->pledges()->contributors()->whereNotNull('phone')->get();

        if ($pledgers->isEmpty()) {
            return back()->withErrors(['meeting_message' => 'No contacts with a phone number to message.']);
        }

        $result = $sms->sendBulk($message, $pledgers);

        return back()->with('status', $result['successful']
            ? "Meeting invitation sent via SMS to {$result['valid']} contact(s)."
                .(($result['invalid'] ?? 0) > 0 ? " {$result['invalid']} number(s) were invalid." : '')
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
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
    public function broadcastSms(Request $request, MessageTemplateService $messages, PhoneNumberService $phones, BeemSmsService $sms): RedirectResponse
    {
        $event = app('currentEvent');
        abort_unless($event->isFuneral() && $event->hasFeature('money'), 404);

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

        $recipients = $numbers->map(fn ($phone) => (object) ['phone' => $phone]);
        $result = $sms->sendBulk($messages->forAnnouncement($event, null), $recipients);

        return back()->with('status', $result['successful']
            ? "Announcement sent via SMS to {$result['valid']} contact(s)."
                .(($result['invalid'] ?? 0) > 0 ? " {$result['invalid']} number(s) were invalid." : '')
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }
}