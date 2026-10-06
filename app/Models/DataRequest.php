<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRequest extends Model
{
    public const TYPES = ['access', 'correct', 'delete', 'stop', 'other'];

    protected $fillable = ['type', 'name', 'phone', 'email', 'details', 'note', 'status', 'acknowledged_at', 'closed_at'];

    /** Where the request stands against our own targets: acknowledge in 2 days, finish in 30. */
    public function urgency(): string
    {
        if ($this->status === 'closed') {
            return 'done';
        }

        if ($this->created_at->lt(now()->subDays(30))) {
            return 'overdue';
        }

        if ($this->status === 'new' && $this->created_at->lt(now()->subDays(2))) {
            return 'late-ack';
        }

        return 'ok';
    }

    protected $casts = ['acknowledged_at' => 'datetime', 'closed_at' => 'datetime'];
}
