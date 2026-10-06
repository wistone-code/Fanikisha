<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\ThankYouService;
use Illuminate\Console\Command;

class SendThankYous extends Command
{
    protected $signature = 'thankyou:send-due';

    protected $description = 'Send the after-event thank-you messages for events that have it switched on.';

    public function handle(ThankYouService $thanks): int
    {
        $today = now()->startOfDay();

        foreach (Event::where('thank_you_enabled', true)->get() as $event) {
            $daysAfter = (int) $event->event_date->startOfDay()->diffInDays($today, false);

            if ($daysAfter < 1 || $daysAfter > ThankYouService::MAX_DAYS_AFTER) {
                continue;
            }

            [$h, $m] = array_map('intval', explode(':', $event->thank_you_time ?: '09:00'));

            if (now()->lt($today->copy()->setTime($h, $m))) {
                continue;
            }

            $result = $thanks->send($event);

            if ($result) {
                $this->info("Event #{$event->id}: ".($result['successful'] ? "thanked {$result['sent']}" : 'FAILED - '.($result['error'] ?? 'unknown')));
            }
        }

        return self::SUCCESS;
    }
}
