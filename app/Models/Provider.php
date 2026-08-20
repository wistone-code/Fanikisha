<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'service', 'budget', 'paid', 'phone'];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'paid' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function remaining(): float
    {
        return (float) $this->budget - (float) $this->paid;
    }
}
