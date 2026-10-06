<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageOptOut extends Model
{
    protected $table = 'message_optouts';

    protected $fillable = ['phone', 'source', 'event_id'];
}
