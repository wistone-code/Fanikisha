<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Checks every event for a due automatic reminder broadcast. Runs every 15
// minutes so it lines up with the ±7 minute window used in isDue().
Schedule::command('reminders:send-due')->everyFifteenMinutes()->withoutOverlapping(30);

// E-card automation. All of these are safe to run often: each guest is messaged at most once.
Schedule::command('cards:remind-unopened')->hourly()->withoutOverlapping(60);
Schedule::command('reminders:event-day')->everyFifteenMinutes()->withoutOverlapping(30);
Schedule::command('thankyou:send-due')->hourly()->withoutOverlapping(60);
Schedule::command('cards:prune')->dailyAt('03:30');
