<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Checks every event for a due automatic reminder broadcast. Runs every 15
// minutes so it lines up with the ±7 minute window used in isDue().
Schedule::command('reminders:send-due')->everyFifteenMinutes();
