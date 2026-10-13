<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Saving the reminder settings also saved the old default wording ("you haven't opened your invitation").
 * Those events go back to the default, so they get the new message that asks guests to confirm attendance.
 * Messages a host actually wrote themselves are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')->whereIn('unopened_reminder_message', [
            "Dear {name}, we noticed you haven't opened your invitation to {event} ({date}) yet. Tap here to see it and RSVP: {link}",
            'Habari {name}, bado hujafungua kadi yako ya mwaliko wa {event} ({date}). Bofya hapa: {link}',
        ])->update(['unopened_reminder_message' => null]);
    }

    public function down(): void
    {
        // Nothing to undo: the default message is used again either way.
    }
};
