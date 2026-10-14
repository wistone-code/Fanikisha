<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A short note from the System Admin to an event's host, shown as a banner until the host taps "Got it". */
class AdminMessage extends Model
{
    protected $fillable = ['event_id', 'body', 'sent_sms', 'created_by', 'dismissed_at'];

    protected function casts(): array
    {
        return ['sent_sms' => 'boolean', 'dismissed_at' => 'datetime'];
    }
}
