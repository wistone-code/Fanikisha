<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingTable extends Model
{
    protected $fillable = ['event_id', 'seating_area_id', 'name', 'capacity', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(SeatingArea::class, 'seating_area_id');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Pledge::class, 'seating_table_id');
    }

    /** Seats taken: each guest plus their confirmed plus-ones, and a double card counts as two people. */
    public function seatsTaken(): int
    {
        // Guests whose card was cancelled or reset no longer appear on the seating list, so they must not use up seats.
        return (int) $this->guests->filter(fn (Pledge $g) => filled($g->invite_token))->sum(fn (Pledge $g) => $g->headcount());
    }
}
