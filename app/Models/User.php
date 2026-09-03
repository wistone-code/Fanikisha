<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password',
        'is_super_user', 'must_change_password', 'is_suspended', 'created_by',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_super_user' => 'boolean',
            'must_change_password' => 'boolean',
            'is_suspended' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * The account that created this one (System Admin, or an event admin via Team
     * Management). Displayed as "Created by" in User Management.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdAccounts(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    /** Every event this user belongs to, with their role on each. */
    public function eventMemberships(): HasMany
    {
        return $this->hasMany(EventMember::class);
    }

    /** The single event this account owns (created), if any — accounts own at most one. */
    public function ownedEvent(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    /**
     * The one event this user currently has access to (owner or invited member).
     * Accounts are limited to a single event, so this is effectively unique per user.
     */
    public function currentEvent(): ?Event
    {
        return $this->eventMemberships()->with('event')->first()?->event;
    }

    public function roleOn(Event $event): ?string
    {
        return $this->eventMemberships()->where('event_id', $event->id)->value('role');
    }

    public function isAdminOn(Event $event): bool
    {
        return $this->roleOn($event) === 'admin';
    }
}
