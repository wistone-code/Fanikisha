<?php

namespace App\Services;

use App\Models\AdminMessage;
use App\Models\Event;

/**
 * The single banner shown at the top of an event's pages. Only one message at a time, by priority:
 *   1. SMS quota finished (sending is paused, the host must act)
 *   2. a message from the System Admin (until dismissed)
 *   3. the event-day best wishes (on the event date only, until dismissed)
 *   4. SMS quota running low
 */
class EventBanner
{
    /** @return array{kind: string, text: string, dismissible: bool}|null */
    public function for(?Event $event, ?string $hostName = null): ?array
    {
        if (! $event) {
            return null;
        }

        $remaining = $event->sms_quota === null ? null : max(0, $event->sms_quota - $event->sms_sent_count);

        if ($remaining === 0) {
            return ['kind' => 'quota_finished', 'text' => 'Your SMS quota is finished — sending is paused. Contact your system admin to raise it.', 'dismissible' => false];
        }

        $message = AdminMessage::where('event_id', $event->id)->whereNull('dismissed_at')->latest('id')->first();

        if ($message) {
            return ['kind' => 'admin', 'text' => $message->body, 'dismissible' => true];
        }

        if ($this->isEventDay($event) && ! ($event->wishes_dismissed_on && $event->wishes_dismissed_on->isToday())) {
            return ['kind' => 'wishes', 'text' => $this->wishes($event, $hostName), 'dismissible' => true];
        }

        if ($remaining !== null && $remaining < 100) {
            return ['kind' => 'quota_low', 'text' => "Your SMS quota is running low — {$remaining} message(s) remaining.", 'dismissible' => false];
        }

        return null;
    }

    public function isEventDay(Event $event): bool
    {
        return $event->event_date !== null && $event->event_date->isToday();
    }

    /** Hides the banner the host is looking at: the admin message, or today's wishes. */
    public function dismiss(Event $event): void
    {
        $open = AdminMessage::where('event_id', $event->id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);

        if (! $open && $this->isEventDay($event)) {
            $event->forceFill(['wishes_dismissed_on' => today()])->save();
        }
    }

    public function wishes(Event $event, ?string $hostName = null): string
    {
        $sw = $event->sms_language === 'sw';
        $first = trim(explode(' ', trim((string) $hostName))[0] ?? '');
        $name = $event->name;
        $type = $event->event_type;

        if ($type === 'Funeral') {
            return $sw
                ? ($first ? "Pole sana {$first}. " : 'Pole sana. ')."Fanikisha iko pamoja nawe na familia yako leo. Siku iende kwa amani na faraja. Tupo hapa ukituhitaji."
                : ($first ? "Dear {$first}, " : '')."Fanikisha is with you and your family today. May the day pass with peace and comfort. We are here if you need us.";
        }

        if ($type === 'Corporate') {
            return $sw
                ? ($first ? "Habari za asubuhi {$first}. " : 'Habari za asubuhi. ')."Leo ni {$name}. Fanikisha inakutakia hafla yenye mafanikio na utulivu. Tupo hapa ukituhitaji."
                : ($first ? "Good morning {$first}. " : 'Good morning. ')."Today is {$name}. Fanikisha wishes you a smooth and successful event. We are here if you need us.";
        }

        return $sw
            ? ($first ? "Habari za asubuhi {$first}! " : 'Habari za asubuhi! ')."Leo ni {$name}. Fanikisha inakutakia siku ya furaha na yenye mafanikio. Tupo hapa ukituhitaji."
            : ($first ? "Good morning {$first}! " : 'Good morning! ')."Today is {$name}. Fanikisha wishes you a joyful and smooth day. We are here if you need us.";
    }
}
