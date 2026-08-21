<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pledge extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'phone', 'amount', 'paid', 'invite_token', 'pay_token'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function committeeMemberships(): HasMany
    {
        return $this->hasMany(CommitteeMember::class);
    }

    public function remaining(): float
    {
        return (float) $this->amount - (float) $this->paid;
    }

    public function isPaidInFull(): bool
    {
        return (float) $this->amount > 0 && $this->remaining() <= 0;
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

    public function inviteLink(): ?string
    {
        return $this->invite_token ? route('guest.rsvp', $this->invite_token) : null;
    }

    /** Always available, unlike inviteLink() — pay_token exists from creation. */
    public function payLink(): string
    {
        return route('guest.pay', $this->pay_token);
    }
}
