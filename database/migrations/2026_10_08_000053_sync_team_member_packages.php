<?php

use App\Models\Event;
use App\Models\EventMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Every account on an event shows the event's package (team members used to keep the default). */
return new class extends Migration
{
    public function up(): void
    {
        Event::query()->select(['id', 'package'])->orderBy('id')->each(function (Event $event) {
            $ids = EventMember::where('event_id', $event->id)->pluck('user_id');
            DB::table('users')->whereIn('id', $ids)->where('is_super_user', false)->update(['package' => $event->package ?: 'full']);
        });
    }

    public function down(): void
    {
        // Data fix only; nothing to undo.
    }
};
