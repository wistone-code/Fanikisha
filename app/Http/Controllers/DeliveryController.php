<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Pledge;
use App\Services\BeemSmsService;
use App\Services\CardTracker;
use App\Services\MessageTemplateService;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Delivery funnel: sent → opened → responded → arrived, plus card revoke/reissue. */
class DeliveryController extends Controller
{
    use AuthorizesEventOwnership;

    public const STAGES = ['not_sent', 'sent', 'opened', 'responded', 'arrived'];

    public function index(Request $request, CardTracker $tracker): View
    {
        $event = app('currentEvent');
        $isAdmin = $request->user()->isAdminOn($event);

        $all = $event->pledges()->whereNotNull('invite_token')->orderBy('name')->get();
        $stage = $request->query('stage');
        $shared = $tracker->sharedCards($event->id);

        $counts = [
            'total' => $all->count(),
            'sent' => $all->whereNotNull('invite_sent_at')->count(),
            'opened' => $all->whereNotNull('first_opened_at')->count(),
            'responded' => $all->whereNotNull('rsvp_status')->count(),
            'arrived' => $all->whereNotNull('checked_in_at')->count(),
            'not_sent' => $all->whereNull('invite_sent_at')->count(),
            'unopened' => $all->whereNotNull('invite_sent_at')->whereNull('first_opened_at')->count(),
        ];

        $rows = $all->filter(fn (Pledge $p) => match ($stage) {
            'not_sent' => $p->invite_sent_at === null,
            'unopened' => $p->invite_sent_at !== null && $p->first_opened_at === null,
            'opened' => $p->first_opened_at !== null && $p->rsvp_status === null,
            'responded' => $p->rsvp_status !== null,
            'arrived' => $p->checked_in_at !== null,
            'flagged' => isset($shared[$p->id]) || $p->scan_attempts > 0,
            default => true,
        })->values();

        return view('event.guests.delivery', [
            'event' => $event,
            'isAdmin' => $isAdmin,
            'counts' => $counts,
            'rows' => $rows,
            'stage' => $stage,
            'shared' => $shared,
            'revoked' => $event->pledges()->whereNull('invite_token')->whereNotNull('invite_revoked_at')->orderBy('name')->get(),
        ]);
    }

    public function markSent(Pledge $pledge): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        $pledge->update(['invite_sent_at' => $pledge->invite_sent_at ?? now(), 'invite_channel' => $pledge->invite_channel ?? 'manual']);

        return back()->with('status', "{$pledge->name} marked as sent");
    }

    /** Sends every card that has not been sent yet, each with its own personal link. */
    public function sendAll(MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $event = app('currentEvent');

        $targets = $event->pledges()->whereNotNull('invite_token')->whereNull('invite_sent_at')->whereNotNull('phone')->get();

        if ($targets->isEmpty()) {
            return back()->with('status', 'Nothing to send — every guest with a phone number already has their card.');
        }

        $result = $sms->sendPersonalised($targets->map(fn (Pledge $p) => (object) [
            'key' => $p->id, 'phone' => $p->phone, 'message' => $messages->forInvitation($event, $p),
        ]));

        $this->markSentIds($result['ok_keys'] ?? [], 'sms');

        return back()->with($result['successful'] ? 'status' : 'error', $this->summary('Cards sent', $result));
    }

    /** Re-sends a nudge to everyone who got their card but has not opened it. */
    public function remindUnopened(MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $event = app('currentEvent');

        $targets = $event->pledges()->whereNotNull('invite_token')->whereNotNull('invite_sent_at')->whereNull('first_opened_at')->whereNotNull('phone')->get();

        if ($targets->isEmpty()) {
            return back()->with('status', 'Everyone who received a card has opened it — nothing to remind.');
        }

        $result = $sms->sendPersonalised($targets->map(fn (Pledge $p) => (object) [
            'key' => $p->id, 'phone' => $p->phone, 'message' => $messages->forUnopenedReminder($event, $p),
        ]));

        Pledge::whereIn('id', $result['ok_keys'] ?? [])->update(['unopened_reminded_at' => now()]);

        return back()->with($result['successful'] ? 'status' : 'error', $this->summary('Reminders sent', $result));
    }

    public function remindWhatsApp(Pledge $pledge, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        if ($blocked = app(\App\Services\OptOutService::class)->blockedRedirect($pledge->phone)) {
            return $blocked;
        }
        abort_unless($pledge->invite_token, 404);

        $pledge->update(['unopened_reminded_at' => now()]);
        $text = rawurlencode($messages->forUnopenedReminder(app('currentEvent'), $pledge));

        return redirect()->away('https://wa.me/'.$phones->digitsOnly($pledge->phone)."?text={$text}");
    }

    public function updateAuto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_remind_unopened' => ['nullable', 'boolean'],
            'auto_remind_unopened_days' => ['required', 'integer', 'min:1', 'max:30'],
            'unopened_reminder_message' => ['nullable', 'string', 'max:1000'],
        ]);

        app('currentEvent')->update([
            'auto_remind_unopened' => $request->boolean('auto_remind_unopened'),
            'auto_remind_unopened_days' => $data['auto_remind_unopened_days'],
            'unopened_reminder_message' => $data['unopened_reminder_message'] ?? null,
        ]);

        return back()->with('status', 'Automatic reminder settings saved');
    }

    // ---- Card security (revoke / reissue) ------------------------------------------------

    public function revoke(Pledge $pledge): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        abort_unless($pledge->invite_token, 404);

        $this->retireToken($pledge);
        $pledge->update(['invite_token' => null, 'invite_revoked_at' => now()]);

        return back()->with('status', "{$pledge->name}'s card link no longer works. Reissue it to give them a new one.");
    }

    public function reissue(Pledge $pledge): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        if ($pledge->invite_token) {
            $this->retireToken($pledge);
        }

        $pledge->update([
            'invite_token' => Str::random(32),
            'invite_revoked_at' => null,
            'invite_sent_at' => null,
            'invite_channel' => null,
            'first_opened_at' => null,
            'last_opened_at' => null,
            'open_count' => 0,
            'scan_attempts' => 0,
        ]);

        DB::table('card_views')->where('pledge_id', $pledge->id)->delete();

        return back()->with('status', "New card link created for {$pledge->name} — send it to them again. The old link is cancelled.");
    }

    // ---- CSV of everything about each guest ----------------------------------------------

    public function export(): StreamedResponse
    {
        $event = app('currentEvent');
        $users = DB::table('users')->pluck('name', 'id');

        $rows = $event->pledges()->with(['seatingTable', 'seatingArea'])->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows, $users, $event) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 names correctly
            fputcsv($out, ['Name', 'Phone', 'Card code', 'Card', 'Group', 'Stage', 'Sent', 'Channel', 'First opened', 'Opens', 'RSVP', 'Responded at', 'Extra guests', 'Total people', 'Meal', 'Dietary', 'Message', 'Seat', 'Checked in', 'Checked in by']);

            foreach ($rows as $p) {
                fputcsv($out, array_map([$this, 'safe'], [
                    $p->name, $p->phone, $p->card_code, $p->card_type, $p->group_name, $p->invite_token ? $p->funnelStage() : 'no card',
                    $p->invite_sent_at?->format('Y-m-d H:i'), $p->invite_channel, $p->first_opened_at?->format('Y-m-d H:i'), $p->open_count,
                    $p->rsvpLabel(), $p->rsvp_at?->format('Y-m-d H:i'), $p->plus_ones, $p->headcount(), $p->meal_choice, $p->dietary_note, $p->host_message,
                    $p->seatLabel(), $p->checked_in_at?->format('Y-m-d H:i'), $p->checked_in_by ? ($users[$p->checked_in_by] ?? '') : '',
                ]));
            }

            fclose($out);
        }, Str::slug($event->name).'-guests.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops a spreadsheet from running a guest-typed value such as "=HYPERLINK(...)" as a formula. */
    public function safe(mixed $value): string
    {
        $text = (string) ($value ?? '');

        // A plain phone number such as +255712345678 is safe, and must stay clean so it can be used as a number.
        if (preg_match('/^\+\d[\d ]*$/', $text)) {
            return $text;
        }

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }

    // ---- helpers --------------------------------------------------------------------------

    private function retireToken(Pledge $pledge): void
    {
        DB::table('card_revocations')->insertOrIgnore([
            'event_id' => $pledge->event_id,
            'pledge_id' => $pledge->id,
            'token_hash' => hash('sha256', $pledge->invite_token),
            'created_at' => now(),
        ]);
    }

    private function markSentIds(array $ids, string $channel): void
    {
        if ($ids) {
            Pledge::whereIn('id', $ids)->update(['invite_sent_at' => now(), 'invite_channel' => $channel]);
        }
    }

    private function summary(string $label, array $result): string
    {
        if (! $result['successful']) {
            return 'SMS send failed: '.($result['error'] ?? 'Unknown error');
        }

        return "{$label}: {$result['sent']} SMS".($result['failed'] > 0 ? ", {$result['failed']} failed (check the numbers)" : '').'.';
    }
}
