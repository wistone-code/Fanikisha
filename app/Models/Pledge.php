<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pledge extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'phone', 'amount', 'paid', 'invite_token', 'pay_token', 'checked_in_at', 'checked_in_by', 'card_type', 'rsvp_status', 'rsvp_at',
        'invite_sent_at', 'invite_channel', 'first_opened_at', 'last_opened_at', 'open_count', 'unopened_reminded_at',
        'card_code', 'plus_ones', 'meal_choice', 'dietary_note', 'host_message',
        'group_name', 'seating_table_id', 'seating_area_id', 'seat_number',
        'thank_you_sent_at', 'feedback_rating', 'feedback_message', 'feedback_at', 'feedback_code', 'invite_revoked_at', 'scan_attempts', 'event_day_reminder_sent_at',
        'guest_only', 'on_invite_list',
        'whatsapp_message_id', 'whatsapp_status', 'whatsapp_status_at', 'whatsapp_error',
    ];

    protected static function booted(): void
    {
        static::creating(function (Pledge $pledge) {
            if (empty($pledge->card_code) && $pledge->event_id) {
                $pledge->card_code = app(\App\Services\CardCodeService::class)->uniqueFor($pledge->event_id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid' => 'decimal:2',
            'checked_in_at' => 'datetime',
            'rsvp_at' => 'datetime',
            'invite_sent_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'unopened_reminded_at' => 'datetime',
            'thank_you_sent_at' => 'datetime',
            'invite_revoked_at' => 'datetime',
            'whatsapp_status_at' => 'datetime',
            'event_day_reminder_sent_at' => 'datetime',
            'guest_only' => 'boolean',
            'on_invite_list' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function seatingTable(): BelongsTo
    {
        return $this->belongsTo(SeatingTable::class);
    }

    public function seatingArea(): BelongsTo
    {
        return $this->belongsTo(SeatingArea::class);
    }

    /** Guests/pledgers who can actually receive a card: link active and not revoked. */
    public function scopeWithLiveCard($q)
    {
        return $q->whereNotNull('invite_token');
    }

    /** "Table 5" / "Zone A" / null — what the guest should be told on the card and at the door. */
    public function seatLabel(): ?string
    {
        if ($this->seatingTable) {
            return $this->seatingTable->name.($this->seat_number ? " · seat {$this->seat_number}" : '');
        }

        return $this->seatingArea?->name;
    }

    /** Where this guest is in the delivery funnel. */
    public function funnelStage(): string
    {
        if ($this->checked_in_at) {
            return 'arrived';
        }

        if ($this->rsvp_status) {
            return 'responded';
        }

        if ($this->first_opened_at) {
            return 'opened';
        }

        return $this->invite_sent_at ? 'sent' : 'not_sent';
    }

    public function committeeMemberships(): HasMany
    {
        return $this->hasMany(CommitteeMember::class);
    }

    /**
     * Pledgers who still owe something. Anyone who has cleared their whole pledge (paid >= amount) is NOT
     * in this set, so they never receive a reminder or the broadcast — they get a thank-you instead.
     */
    public function scopeOutstanding($query)
    {
        return $query->where('guest_only', false)->whereColumn('paid', '<', 'amount');
    }

    /** Real contributors only — invited guests (guest_only) never count in pledges, finance or reminders. */
    public function scopeContributors($query)
    {
        return $query->where('guest_only', false);
    }

    /**
     * Guests to remind: their card went out but they have not said whether they are coming — whether they never
     * opened it, or opened it and did not answer. Anyone already checked in is left alone.
     */
    public function scopeAwaitingReply($query)
    {
        return $query->whereNotNull('invite_token')->whereNotNull('invite_sent_at')
            ->whereNull('rsvp_status')->whereNull('checked_in_at');
    }

    public function remaining(): float
    {
        return max(0.0, (float) $this->amount - (float) $this->paid);
    }

    public function isPaidInFull(): bool
    {
        return (float) $this->amount > 0 && $this->remaining() <= 0;
    }

    /** People this card covers: the guest, a partner on a double card, and any plus-ones they confirmed. */
    public function headcount(): int
    {
        if ($this->rsvp_status === 'not_attending') {
            return 0;
        }

        return 1 + ($this->card_type === 'double' ? 1 : 0) + (int) $this->plus_ones;
    }

    public function isCheckedIn(): bool
    {
        return ! empty($this->checked_in_at);
    }

    /** "Attending" / "Not attending" / "Awaiting response" — for display, e.g. on the Pledges list. */
    public function rsvpLabel(): string
    {
        return match ($this->rsvp_status) {
            'attending' => 'Attending',
            'not_attending' => 'Not attending',
            default => 'Awaiting response',
        };
    }

    /** Completed / Overdue / Pending — used by the Pledge status donut chart. */
    public function status(): string
    {
        if ($this->isPaidInFull()) {
            return 'Completed';
        }

        if ($this->event->pledge_deadline?->isPast()) {
            return 'Overdue';
        }

        return 'Pending';
    }

    /**
     * An invitation goes to each guest once. It counts as sent from the first SMS, WhatsApp or "mark as sent",
     * except a WhatsApp invitation that Meta reported as failed (it never arrived, so SMS is still allowed).
     * To send again on purpose, the host resets the guest's link, which clears this.
     */
    /** A short random code (the same one for the short card link and the feedback link), so SMS links stay short. */
    public function shortCode(): string
    {
        if (! $this->feedback_code) {
            do {
                $code = \Illuminate\Support\Str::random(8);
            } while (static::where('feedback_code', $code)->exists());

            $this->feedback_code = $code;
            if ($this->exists) {
                $this->forceFill(['feedback_code' => $code])->save();
            }
        }

        return $this->feedback_code;
    }

    /** The guest's own page to rate the event: a short link, so the thank-you SMS stays within one SMS. */
    public function feedbackLink(): ?string
    {
        return $this->invite_token ? route('guest.feedback', $this->shortCode()) : null;
    }

    /** The card link for SMS: about 32 characters instead of 58, and it opens the same card. */
    public function shortCardLink(): ?string
    {
        return $this->invite_token ? route('guest.card.short', $this->shortCode()) : null;
    }

    public function invitationAlreadySent(): bool
    {
        return $this->invite_sent_at !== null
            && ! ($this->invite_channel === 'whatsapp' && $this->whatsapp_status === 'failed');
    }

    /**
     * A guest who got the invitation on WhatsApp may be on a basic phone without WhatsApp, so one normal SMS is
     * still allowed after it. After an SMS, nothing more goes out until the link is reset.
     */
    public function smsInvitationAllowed(): bool
    {
        return ! $this->invitationAlreadySent() || $this->invite_channel === 'whatsapp';
    }

    /** Short label for an invitation that was already sent, e.g. "Sent · WhatsApp · Oct 12". */
    public function invitationSentLabel(): string
    {
        $channel = match ($this->invite_channel) {
            'sms' => $this->whatsapp_message_id ? 'WhatsApp + SMS' : 'SMS',
            'whatsapp' => 'WhatsApp',
            'manual' => 'marked sent',
            default => null,
        };

        return implode(' · ', array_filter(['Sent', $channel, $this->invite_sent_at?->format('M j')]));
    }

    public function inviteLink(): ?string
    {
        return $this->invite_token ? route('guest.rsvp', $this->invite_token) : null;
    }

    /** The pay page for SMS reminders, as a short link. */
    public function shortPayLink(): string
    {
        return route('guest.pay.short', $this->shortCode());
    }

    /** Always available, unlike inviteLink() — pay_token exists from creation. */
    public function payLink(): string
    {
        return route('guest.pay', $this->pay_token);
    }
}
