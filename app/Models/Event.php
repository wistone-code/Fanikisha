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
        'name', 'event_type', 'place', 'event_date', 'pledge_deadline', 'created_by',
        'provider_message', 'reminder_message', 'broadcast_message',
        'invitation_message', 'meeting_message', 'announcement_message', 'committee_message',
        'schedule_message',
        'reminder_auto_enabled', 'reminder_auto_frequency_days', 'reminder_auto_time', 'reminder_auto_last_sent_at',
        'sms_quota', 'sms_sent_count', 'card_photo', 'card_photo_mime',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'pledge_deadline' => 'date',
            'reminder_auto_enabled' => 'boolean',
            'reminder_auto_last_sent_at' => 'datetime',
        ];
    }

    /** Event types that hide the home-page countdown ring and lead the dashboard with the event day instead of a "days left" counter. */
    public const NO_COUNTDOWN_TYPES = ['Graduation', 'Baptism', 'Funeral'];

    public const TYPES = [
        'Wedding', 'Engagement', 'Send-off', 'Kitchen Party', 'Baby Shower',
        'Birthday', 'Graduation', 'Baptism', 'Confirmation', 'Communion',
        'Funeral', 'Corporate',
    ];

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

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(ScheduleItem::class)->orderBy('date')->orderBy('time');
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

    public function showsCountdown(): bool
    {
        return ! in_array($this->event_type, self::NO_COUNTDOWN_TYPES, true);
    }

    /** Aggregate financial figures used across the Home and Financial Status screens. */
    public function stats(): array
    {
        $totalPledged = $this->pledges()->sum('amount');
        $collected = $this->pledges()->sum('paid');
        $budget = $this->providers()->sum('budget');

        return [
            'total_pledged' => (float) $totalPledged,
            'collected' => (float) $collected,
            'remain' => (float) ($totalPledged - $collected),
            'budget' => (float) $budget,
            'variance' => (float) ($budget - $collected),
            'pledge_count' => $this->pledges()->count(),
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
     */
    public function messageOrDefault(string $surface): string
    {
        $column = "{$surface}_message";

        return $this->{$column} ?: match ($surface) {
            'provider' => 'Hello {name}, confirming your booking as our {service} provider for {event}. Budget: {budget}. Please reach out if you have any questions.',
            'reminder' => 'Hi {name}, friendly reminder on {event} contribution: pledged {pledged}, paid {paid} so far, {remain} remaining. Thank you!',
            'invitation' => "You're invited to {event}! Join us on {date}".($this->place ? ' at {place}' : '').'. Tap your link to RSVP: {link}',
            'announcement' => 'Habari {name}, this is to inform you about {event}'.($this->place ? ' at {place}' : '').' on {date}. Your presence and support mean a lot to the family. Thank you.',
            'committee' => 'Hello {name}, you have been elected as {role} on {committee} committee.',
            default => '',
        };
    }
}