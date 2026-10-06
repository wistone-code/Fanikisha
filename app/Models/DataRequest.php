<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRequest extends Model
{
    public const TYPES = ['access', 'correct', 'delete', 'stop', 'other'];

    protected $fillable = ['type', 'name', 'phone', 'email', 'details', 'status', 'acknowledged_at', 'closed_at'];

    protected $casts = ['acknowledged_at' => 'datetime', 'closed_at' => 'datetime'];
}
