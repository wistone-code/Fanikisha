<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAsset extends Model
{
    protected $fillable = ['event_id', 'kind', 'mime', 'data'];
}
