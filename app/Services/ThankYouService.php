<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Pledge;

/** After-event thank-you texts: one wording for guests who came, another for those who could not. Sent once per person. */
class ThankYouService
{
    /** Stop sending this many days after the event, so switching it on much later does not text people about an old event. */
    public const MAX_DAYS_AFTER = 14;

    public function __construct(private MessageTemplateService $messages, private BeemSmsService $sms) {}

    public function targets(Event $event)
    {
        return $event->pledges()
            ->whereNotNull('phone')->whereNull('thank_you_sent_at')
            ->where(fn ($q) => $q->whereNotNull('invite_token')->orWhere('paid', '>', 0))
            ->get();
    }

    /** @return array{successful: bool, sent: int, failed: int, error?: string}|null null when nobody is waiting */
    public function send(Event $event): ?array
    {
        $targets = $this->targets($event);

        if ($targets->isEmpty()) {
            return null;
        }

        $result = $this->sms->forEvent($event)->sendPersonalised($targets->map(fn (Pledge $p) => (object) [
            'key' => $p->id, 'phone' => $p->phone, 'message' => $this->messages->forThankYou($event, $p),
        ]), fn ($id) => Pledge::whereKey($id)->update(['thank_you_sent_at' => now()]), partial: true);

        if (! empty($result['ok_keys'])) {
            Pledge::whereIn('id', $result['ok_keys'])->update(['thank_you_sent_at' => now()]);
        }

        return $result;
    }
}
