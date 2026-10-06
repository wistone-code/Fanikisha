<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPhoto extends Model
{
    protected $fillable = ['event_id', 'uploader_key', 'uploader_name', 'thumb', 'image', 'size', 'hidden', 'reports'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
