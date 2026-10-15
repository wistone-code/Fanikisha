<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Saving a settings page also saved the default wording as if the host had written it. Where a stored message is
 * exactly the old long default, it goes back to "default" so the event gets the shorter one-SMS wording.
 * Anything a host changed is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $old = [
            'reminder_message' => [
                'Habari {name}, kikumbusho cha mchango wako wa {event}: uliahidi {pledged}, umeshalipa {paid}, umebakiza {remain}. Lipa hapa: {pay_link}. Asante!',
                'Dear {name}, friendly reminder on {event} contribution: pledged {pledged}, paid {paid} so far, {remain} remaining. Pay here: {pay_link}. Thank you!',
            ],
            'unopened_reminder_message' => [
                'Habari {name}, bado hatujapokea jibu lako kwa mwaliko wa {event} tarehe {date}. Tafadhali fungua kadi yako sasa na uchague "Ndiyo, nitakuwepo" au "Siwezi kuja": {link} Jibu lako linatusaidia kupanga. Asante!',
                'Dear {name}, we have not yet received your reply to the invitation to {event} on {date}. Please open your card now and tap "Yes, I\'ll be there" or "Can\'t make it": {link} Your answer helps us plan. Thank you!',
            ],
            'event_day_reminder_message' => [
                'Habari {name}, leo ni {event}! Tarehe {date}{time}{place}. Kadi yako (QR ya kuingia): {link} Namba: {code}',
                'Dear {name}, today is {event}! {date}{time}{place}. Your card and entry QR: {link} Entry code: {code}',
            ],
            'invitation_message' => [
                'Habari {name}, umealikwa kwenye {event}! Tujiunge tarehe {date}. Bofya kiungo chako kuthibitisha: {link} Namba yako ya kuingia: {code}',
                'Habari {name}, umealikwa kwenye {event}! Tujiunge tarehe {date} katika {place}. Bofya kiungo chako kuthibitisha: {link} Namba yako ya kuingia: {code}',
                "Dear {name}, you're invited to {event}! Join us on {date}. Tap your link to RSVP: {link} Your entry code: {code}",
                "Dear {name}, you're invited to {event}! Join us on {date} at {place}. Tap your link to RSVP: {link} Your entry code: {code}",
            ],
        ];

        foreach ($old as $column => $texts) {
            DB::table('events')->whereIn($column, $texts)->update([$column => null]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the default message is used again either way.
    }
};
