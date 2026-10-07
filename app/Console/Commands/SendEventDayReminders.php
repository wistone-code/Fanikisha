<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventDayReminderService;
use Illuminate\Console\Command;

class SendEventDayReminders extends Command
{
    protected $signature = 'reminders:event-day';

    protected $description = 'On the event day, text each guest the time, venue and their card link (once per guest).';

    public function handle(EventDayReminderService $service): int
    {
        $today = now()->toDateString();

        foreach (Event::lean()->where('event_day_reminder_enabled', true)->whereDate('event_date', $today)->get() as $event) {
            [$h, $m] = array_map('intval', explode(':', $event->event_day_reminder_time ?: '07:00'));

            if (now()->lt(now()->startOfDay()->setTime($h, $m))) {
                continue;
            }

            $result = $service->send($event);

            if ($result) {
                $this->info("Event #{$event->id}: ".($result['successful'] ? "reminded {$result['sent']}" : 'FAILED - '.($result['error'] ?? 'unknown')));
            }
        }

        return self::SUCCESS;
    }
}
