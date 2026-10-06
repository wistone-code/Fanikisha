<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A visitor's request for a Fanikisha account, sent from the public landing page. */
class AccountRequest extends Model
{
    public const EVENT_TYPES = ['wedding', 'sendoff', 'funeral', 'graduation', 'fundraiser', 'birthday', 'corporate', 'other'];

    public const NEEDS = ['ecards', 'both'];

    public const STATUSES = ['new', 'contacted', 'created', 'declined'];

    protected $fillable = ['name', 'phone', 'email', 'event_type', 'event_date', 'location', 'guests', 'needs', 'language', 'message', 'status', 'note'];

    protected $casts = ['event_date' => 'date'];
}
