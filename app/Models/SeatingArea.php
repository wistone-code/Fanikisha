<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingArea extends Model
{
    protected $fillable = ['event_id', 'name', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(SeatingTable::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Pledge::class, 'seating_area_id');
    }
}
