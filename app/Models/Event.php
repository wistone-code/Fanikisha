<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'event_type', 'mode', 'package', 'theme_color', 'place', 'event_date', 'pledge_deadline', 'created_by',
        'provider_message', 'reminder_message', 'broadcast_message',
        'invitation_message', 'meeting_message', 'announcement_message', 'committee_message',
        'schedule_message',
        'reminder_auto_enabled', 'reminder_auto_frequency_days', 'reminder_auto_time', 'reminder_auto_last_sent_at',
        'sms_quota', 'sms_sent_count', 'card_photo', 'card_photo_mime',
        'payout_phone', 'payout_network', 'couple_threshold_amount', 'sms_language',
        'auto_remind_unopened', 'auto_remind_unopened_days', 'unopened_reminder_message',
        'rsvp_plus_ones_enabled', 'rsvp_max_plus_single', 'rsvp_max_plus_double', 'rsvp_meal_enabled', 'rsvp_meal_options',
        'rsvp_dietary_enabled', 'rsvp_message_enabled', 'rsvp_cutoff_date',
        'seating_mode', 'seating_published',
        'host_names', 'thank_you_enabled', 'thank_you_time', 'thank_you_attended_message', 'thank_you_absent_message', 'thank_you_acknowledge_paid',
        'checkin_confirm_name',
        'photo_wall_enabled', 'photo_wall_token', 'photo_wall_pin', 'photo_wall_access', 'photo_wall_open_mode',
        'photo_wall_close_days', 'photo_wall_max_per_guest', 'photo_wall_max_total', 'photo_wall_uploads_blocked',
        'card_template', 'card_default_lang', 'card_text_en', 'card_text_sw', 'card_video_url', 'card_music_url',
        'card_has_music', 'card_has_custom_design', 'custom_design_width', 'custom_design_height',
        'custom_design_layout', 'use_custom_design',
        'event_time', 'venue_name', 'venue_address', 'venue_lat', 'venue_lng', 'landmark_note_en', 'landmark_note_sw',
        'event_day_reminder_enabled', 'event_day_reminder_time', 'event_day_reminder_message',
    ];

    /** Verified June 2026 against vodacom.co.tz, yas.co.tz/mixx-by-yas, airtel.co.tz, halotel.co.tz. */
    public const NETWORK_USSD_CODES = [
        'M-Pesa' => '*150*00#',
        'Mixx by Yas' => '*150*01#',
        'Airtel Money' => '*150*60#',
        'HaloPesa' => '*150*88#',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'pledge_deadline' => 'date',
            'reminder_auto_enabled' => 'boolean',
            'reminder_auto_last_sent_at' => 'datetime',
            'auto_remind_unopened' => 'boolean',
            'rsvp_plus_ones_enabled' => 'boolean',
            'rsvp_meal_enabled' => 'boolean',
            'rsvp_dietary_enabled' => 'boolean',
            'rsvp_message_enabled' => 'boolean',
            'rsvp_cutoff_date' => 'date',
            'seating_published' => 'boolean',
            'thank_you_enabled' => 'boolean',
            'thank_you_acknowledge_paid' => 'boolean',
            'checkin_confirm_name' => 'boolean',
            'photo_wall_enabled' => 'boolean',
            'photo_wall_uploads_blocked' => 'boolean',
            'use_custom_design' => 'boolean',
            'card_has_music' => 'boolean',
            'card_has_custom_design' => 'boolean',
            'event_day_reminder_enabled' => 'boolean',
        ];
    }

    /** Event types that hide the home-page countdown ring and lead the dashboard with the event day instead of a "days left" counter. */
    public const NO_COUNTDOWN_TYPES = ['Graduation', 'Baptism', 'Funeral'];

    public const TYPES = [
        'Wedding', 'Engagement', 'Send-off', 'Kitchen Party', 'Baby Shower',
        'Birthday', 'Graduation', 'Baptism', 'Confirmation', 'Communion',
        'Funeral', 'Corporate',
    ];

    /** Event types that make sense for an e-card account — everything except Funeral. */
    public static function ecardTypes(): array
    {
        return array_values(array_diff(self::TYPES, ['Funeral']));
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(EventMember::class);
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    public function seatingAreas(): HasMany
    {
        return $this->hasMany(SeatingArea::class)->orderBy('sort_order')->orderBy('id');
    }

    public function seatingTables(): HasMany
    {
        return $this->hasMany(SeatingTable::class)->orderBy('sort_order')->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class);
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(ScheduleItem::class)->orderBy('date')->orderBy('time');
    }

    public const MODE_CONTRIBUTIONS = 'contributions';

    public const MODE_ECARD = 'ecard';

    /** E-card-only accounts: just guests, e-cards, RSVP and check-in — no pledges or finances. */
    public function isEcard(): bool
    {
        return $this->mode === self::MODE_ECARD;
    }

    /** Feature groups this event's package includes (config/packages.php); e-card mode never has money features. */
    public function features(): array
    {
        $features = config('packages.packages.'.($this->package ?: config('packages.default')).'.features', ['money', 'cards']);

        return $this->isEcard() ? array_values(array_diff($features, ['money'])) : $features;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    public function isFuneral(): bool
    {
        return $this->event_type === 'Funeral';
    }

    /** Null quota means unlimited (System Admin hasn't capped this account). */
    public function smsRemaining(): ?int
    {
        return $this->sms_quota === null ? null : max(0, $this->sms_quota - $this->sms_sent_count);
    }

    public function hasSmsCapacity(int $count = 1): bool
    {
        return $this->sms_quota === null || ($this->sms_sent_count + $count) <= $this->sms_quota;
    }

    public function hasCardPhoto(): bool
    {
        return ! empty($this->card_photo);
    }

    public function hasPayoutNumber(): bool
    {
        return ! empty($this->payout_phone);
    }

    public function showsCountdown(): bool
    {
        return ! in_array($this->event_type, self::NO_COUNTDOWN_TYPES, true);
    }

    /** Aggregate financial figures used across the Home and Financial Status screens. */
    public function stats(): array
    {
        $totalPledged = $this->pledges()->contributors()->sum('amount');
        $collected = $this->pledges()->contributors()->sum('paid');
        $budget = $this->providers()->sum('budget');
        $expenditure = $this->providers()->sum('paid');

        return [
            'total_pledged' => (float) $totalPledged,
            'collected' => (float) $collected,
            'remain' => (float) ($totalPledged - $collected),
            'budget' => (float) $budget,
            'expenditure' => (float) $expenditure,
            'balance' => (float) ($collected - $expenditure),
            'variance' => (float) ($budget - $collected),
            'pledge_count' => $this->pledges()->contributors()->count(),
        ];
    }

    /**
     * Returns the saved message for the given surface, or that surface's default
     * template (filled with this event's own name/place/date where relevant).
     * $surface is one of: provider, reminder, broadcast, invitation, meeting,
     * announcement, committee, schedule.
     *
     * "broadcast", "meeting", and "schedule" are deliberately user-defined with NO
     * starter text — the admin must write their own before sending, rather than
     * silently defaulting to placeholder content (which previously included a
     * hardcoded example bank account, easy to send by accident without editing it).
     *
     * Default text (only used when the admin hasn't written their own) follows
     * sms_language — English or Swahili. A saved custom message is sent exactly
     * as written either way, since the admin already chose its wording/language.
     */
    public function messageOrDefault(string $surface, bool $fill = true): string
    {
        $column = "{$surface}_message";
        $sw = $this->sms_language === 'sw';

        $text = $this->{$column} ?: match ($surface) {
            'provider' => $sw
                ? 'Habari {name}, tunathibitisha uteuzi wako kama mtoa huduma wa {service} kwa ajili ya {event}. Bajeti: {budget}. Wasiliana nasi endapo utakuwa na maswali.'
                : 'Dear {name}, confirming your booking as our {service} provider for {event}. Budget: {budget}. Please reach out if you have any questions.',
            'reminder' => $sw
                ? 'Habari {name}, kikumbusho cha mchango wako wa {event}: uliahidi {pledged}, umeshalipa {paid}, umebakiza {remain}. Lipa hapa: {pay_link}. Asante!'
                : 'Dear {name}, friendly reminder on {event} contribution: pledged {pledged}, paid {paid} so far, {remain} remaining. Pay here: {pay_link}. Thank you!',
            'invitation' => $sw
                ? 'Habari {name}, umealikwa kwenye {event}! Tujiunge tarehe {date}'.($this->place ? ' katika {place}' : '').($this->hasFeature('cards') ? '. Bofya kiungo chako kuthibitisha: {link}' : '. Karibu sana!')
                : "Dear {name}, you're invited to {event}! Join us on {date}".($this->place ? ' at {place}' : '').($this->hasFeature('cards') ? '. Tap your link to RSVP: {link}' : '. We look forward to seeing you!'),
            'announcement' => $sw
                ? 'Habari {name}, hii ni taarifa kuhusu {event}'.($this->place ? ' katika {place}' : '').' tarehe {date}. Uwepo na msaada wako una maana kubwa kwa familia. Asante.'
                : 'Dear {name}, this is to inform you about {event}'.($this->place ? ' at {place}' : '').' on {date}. Your presence and support mean a lot to the family. Thank you.',
            'committee' => $sw
                ? 'Habari {name}, umechaguliwa kuwa {role} katika kamati ya {committee}.'
                : 'Dear {name}, you have been elected as {role} on {committee} committee.',
            'unopened_reminder' => $sw
                ? 'Habari {name}, bado hujafungua kadi yako ya mwaliko wa {event} ({date}). Bofya hapa: {link}'
                : "Dear {name}, we noticed you haven't opened your invitation to {event} ({date}) yet. Tap here to see it and RSVP: {link}",
            'thank_you_attended' => $sw
                ? 'Habari {name}, asante kwa kuwa nasi kwenye {event}. Uwepo wako ulituongezea furaha! — {hosts}'
                : 'Dear {name}, thank you for celebrating {event} with us. Your presence made the day special! — {hosts}',
            'thank_you_absent' => $sw
                ? 'Habari {name}, tulikukumbuka kwenye {event}. Asante kwa mawazo na dua zako. — {hosts}'
                : 'Dear {name}, we missed you at {event}. Thank you for your thoughts and good wishes. — {hosts}',
            'event_day_reminder' => $sw
                ? 'Habari {name}, leo ni {event}! Tarehe {date}{time}{place}. Kadi yako (QR ya kuingia): {link}'
                : 'Dear {name}, today is {event}! {date}{time}{place}. Your card and entry QR: {link}',
            default => '',
        };

        // Editors pass $fill = false so the host sees (and keeps) the {event_name} / {event_type} placeholders.
        return $fill ? $this->fillEventFields($text) : $text;
    }

    /** Swahili names for the event types, used by {event_type} when the event's messages are in Swahili. */
    private const TYPES_SW = [
        'Wedding' => 'Harusi', 'Engagement' => 'Uchumba', 'Send-off' => 'Send-off', 'Kitchen Party' => 'Kitchen Party',
        'Baby Shower' => 'Baby Shower', 'Birthday' => 'Sherehe ya Kuzaliwa', 'Graduation' => 'Mahafali', 'Baptism' => 'Ubatizo',
        'Confirmation' => 'Kipaimara', 'Communion' => 'Komunyo', 'Funeral' => 'Msiba', 'Corporate' => 'Hafla',
    ];

    /**
     * Fills {event_name} and {event_type} in any message. Also accepts how people naturally type them —
     * {event name}, {event type}, {event name } — so a pasted template just works.
     */
    public function fillEventFields(string $text): string
    {
        $type = $this->sms_language === 'sw' ? (self::TYPES_SW[$this->event_type] ?? $this->event_type) : $this->event_type;

        $text = preg_replace_callback('/\{\s*event[\s_]*name\s*\}/i', fn () => (string) $this->name, $text) ?? $text;

        return preg_replace_callback('/\{\s*event[\s_]*type\s*\}/i', fn () => (string) $type, $text) ?? $text;
    }


    /** Venue shown on the card and in reminders, in the language asked for ('en' or 'sw'). */
    public function venueLine(): string
    {
        return trim(($this->venue_name ?: $this->place ?: '').($this->venue_address ? ', '.$this->venue_address : ''), ' ,');
    }

    public function hasMapPin(): bool
    {
        return $this->venue_lat !== null && $this->venue_lng !== null;
    }

    /** Directions link: the exact pin if one was set, otherwise a text search on the place name. */
    public function mapsUrl(): ?string
    {
        if ($this->hasMapPin()) {
            return 'https://www.google.com/maps/dir/?api=1&destination='.number_format((float) $this->venue_lat, 7, '.', '').','.number_format((float) $this->venue_lng, 7, '.', '');
        }

        return $this->place ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->venueLine()) : null;
    }
}