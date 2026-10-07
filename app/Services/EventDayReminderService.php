<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Pledge;

/** The "today is the day" SMS: time, venue and the guest's own card link. Sent once per guest. */
class EventDayReminderService
{
    public function __construct(private MessageTemplateService $messages, private BeemSmsService $sms) {}

    /** @return array{successful: bool, sent: int, failed: int, error?: string}|null null when nobody is waiting */
    public function send(Event $event): ?array
    {
        $targets = $event->pledges()
            ->whereNotNull('invite_token')->whereNotNull('phone')
            ->whereNull('event_day_reminder_sent_at')
            ->where(fn ($q) => $q->whereNull('rsvp_status')->orWhere('rsvp_status', '!=', 'not_attending'))
            ->get();

        if ($targets->isEmpty()) {
            return null;
        }

        $result = $this->sms->forEvent($event)->sendPersonalised($targets->map(fn (Pledge $p) => (object) [
            'key' => $p->id, 'phone' => $p->phone, 'message' => $this->messages->forEventDayReminder($event, $p),
        ]), fn ($id) => Pledge::whereKey($id)->update(['event_day_reminder_sent_at' => now()]), partial: true);

        if (! empty($result['ok_keys'])) {
            Pledge::whereIn('id', $result['ok_keys'])->update(['event_day_reminder_sent_at' => now()]);
        }

        return $result;
    }
}
