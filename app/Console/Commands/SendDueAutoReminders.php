<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\BeemSmsService;
use App\Services\MessageTemplateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDueAutoReminders extends Command
{
    protected $signature = 'reminders:send-due';

    protected $description = 'Send the outstanding-pledge reminder broadcast for any event whose auto-reminder schedule is due right now.';

    public function handle(MessageTemplateService $messages, BeemSmsService $sms): int
    {
        $now = now();

        $events = Event::where('reminder_auto_enabled', true)->get();

        if ($events->isEmpty()) {
            $this->info('No events have automatic reminders enabled.');

            return self::SUCCESS;
        }

        foreach ($events as $event) {
            if (! $this->isDue($event, $now)) {
                continue;
            }

            $message = $messages->forBroadcast($event);

            if (trim($message) === '') {
                $this->info("Event #{$event->id} ({$event->name}): skipped, no broadcast message saved.");

                continue;
            }

            $outstanding = $event->pledges()->whereColumn('paid', '<', 'amount')->whereNotNull('phone')->get();

            if ($outstanding->isEmpty()) {
                // Nothing to send, but still mark as checked so it doesn't re-evaluate every run today.
                $event->update(['reminder_auto_last_sent_at' => $now]);
                $this->info("Event #{$event->id} ({$event->name}): skipped, no outstanding pledgers with a phone number.");

                continue;
            }

            $result = $sms->sendBulk($message, $outstanding);
            $event->update(['reminder_auto_last_sent_at' => $now]);

            $this->info($result['successful']
                ? "Event #{$event->id} ({$event->name}): sent to {$result['valid']} pledger(s)."
                : "Event #{$event->id} ({$event->name}): FAILED - ".($result['error'] ?? 'unknown error'));
        }

        return self::SUCCESS;
    }

    /**
     * Due when: current time is within a 15-minute window of the event's chosen
     * send time, AND enough days have passed since the last send (or it's never
     * been sent before). The 15-minute window matches the schedule's run frequency.
     */
    private function isDue(Event $event, Carbon $now): bool
    {
        $target = $event->reminder_auto_time ?? '09:00:00';
        [$hour, $minute] = array_map('intval', explode(':', substr($target, 0, 5)));

        $windowStart = $now->copy()->setTime($hour, $minute)->subMinutes(7);
        $windowEnd = $now->copy()->setTime($hour, $minute)->addMinutes(7);

        if (! $now->between($windowStart, $windowEnd)) {
            return false;
        }

        if (! $event->reminder_auto_last_sent_at) {
            return true;
        }

        $daysSinceLastSend = $event->reminder_auto_last_sent_at->diffInDays($now);

        return $daysSinceLastSend >= max(1, (int) $event->reminder_auto_frequency_days);
    }
}
