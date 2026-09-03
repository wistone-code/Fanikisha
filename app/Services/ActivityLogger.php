<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;

/**
 * Records account- and event-level actions (account created, password reset,
 * event created, and so on) for the System Admin's Logs screen. Deliberately
 * only ever touches account/event metadata — never pledge, provider, or
 * guest content — matching UserManagementController's own privacy boundary.
 */
class ActivityLogger
{
    public static function log(string $action, string $description, ?User $targetUser = null, ?Event $event = null, ?User $actor = null): ActivityLog
    {
        $actor ??= auth()->user();

        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUser?->id,
            'event_id' => $event?->id,
            'action' => $action,
            'description' => $description,
            // Set explicitly via PHP's now() (Africa/Dar_es_Salaam, per config('app.timezone'))
            // rather than relying on the database server's own useCurrent() default — that
            // default runs on the DB server's own clock/timezone (UTC on Railway), which then
            // gets silently mislabeled as already being local time when read back, throwing
            // every displayed timestamp off by the UTC offset.
            'created_at' => now(),
        ]);
    }
}
