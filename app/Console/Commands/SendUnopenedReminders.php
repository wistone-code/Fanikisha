<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Pledge;
use App\Services\BeemSmsService;
use App\Services\MessageTemplateService;
use Illuminate\Console\Command;

class SendUnopenedReminders extends Command
{
    protected $signature = 'cards:remind-unopened';

    protected $description = 'Text guests who were sent their card but have not opened it, N days before the event (once per guest).';

    public function handle(MessageTemplateService $messages, BeemSmsService $sms): int
    {
        $today = now()->startOfDay();

        // Only start sending from 09:00 so people are not texted in the middle of the night.
        if (now()->hour < 9) {
            return self::SUCCESS;
        }

        foreach (Event::lean()->where('auto_remind_unopened', true)->get() as $event) {
            $daysLeft = (int) $today->diffInDays($event->event_date->startOfDay(), false);

            // From N days out until the day before; a guest is reminded once, so a missed run catches up next time.
            if ($daysLeft < 1 || $daysLeft > $event->auto_remind_unopened_days) {
                continue;
            }

            $targets = $event->pledges()->whereNotNull('invite_token')->whereNotNull('invite_sent_at')
                ->whereNull('first_opened_at')->whereNull('unopened_reminded_at')->whereNotNull('phone')->get();

            if ($targets->isEmpty()) {
                continue;
            }

            $result = $sms->forEvent($event)->sendPersonalised($targets->map(fn (Pledge $p) => (object) [
                'key' => $p->id, 'phone' => $p->phone, 'message' => $messages->forUnopenedReminder($event, $p),
            ]), fn ($id) => Pledge::whereKey($id)->update(['unopened_reminded_at' => now()]), partial: true);

            if (! empty($result['ok_keys'])) {
                Pledge::whereIn('id', $result['ok_keys'])->update(['unopened_reminded_at' => now()]);
            }

            $this->info("Event #{$event->id}: ".($result['successful'] ? "reminded {$result['sent']}" : 'FAILED - '.($result['error'] ?? 'unknown')));
        }

        return self::SUCCESS;
    }
}
