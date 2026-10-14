<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Pledge;
use Illuminate\Support\Facades\Log;

/**
 * When a WhatsApp invitation does not reach the guest (number not on WhatsApp, Meta's limit on marketing messages
 * to people who have not chatted with the business, a send error…), the card goes out by normal SMS straight away,
 * so the guest still gets it without the host having to notice and resend. Sent at most once per guest.
 */
class WhatsAppSmsFallback
{
    public function __construct(
        private MessageTemplateService $messages,
        private BeemSmsService $sms,
        private OptOutService $optOuts,
    ) {}

    /** @return bool true when the SMS went out */
    public function send(Pledge $pledge, ?Event $event = null): bool
    {
        $event ??= Event::find($pledge->event_id);

        if (! $event || blank($pledge->phone) || ! $pledge->invite_token || $pledge->invite_channel === 'sms') {
            return false;
        }

        if ($this->optOuts->isOptedOut($pledge->phone) || ! $event->hasSmsCapacity()) {
            return false;
        }

        $result = $this->sms->forEvent($event)->sendSingle($this->messages->forInvitationSms($event, $pledge), $pledge->phone);

        if (! ($result['successful'] ?? false)) {
            Log::warning('WhatsApp→SMS fallback failed', ['pledge' => $pledge->id, 'error' => $result['error'] ?? null]);

            return false;
        }

        $pledge->update(['invite_sent_at' => now(), 'invite_channel' => 'sms']);

        return true;
    }
}
