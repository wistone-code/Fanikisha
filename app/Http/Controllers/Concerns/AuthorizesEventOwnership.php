<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Pledge;
use App\Models\Provider;
use App\Models\ScheduleItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Route-model-bound records (Pledge, Provider, Committee, CommitteeMember,
 * ScheduleItem) are looked up by ID with NO automatic scoping to the requester's
 * event. Without this check, an authenticated admin of Event A could edit/delete/
 * message any record belonging to Event B just by guessing or incrementing an ID
 * — a cross-tenant IDOR. Every controller method that receives one of these models
 * MUST call the matching assert*() below as its first line.
 */
trait AuthorizesEventOwnership
{
    private function assertBelongsToCurrentEvent(Model $model): void
    {
        $event = app('currentEvent');

        abort_unless($event && (int) $model->event_id === (int) $event->id, 404);
    }

    private function assertPledgeInCurrentEvent(Pledge $pledge): void
    {
        $this->assertBelongsToCurrentEvent($pledge);
    }

    private function assertProviderInCurrentEvent(Provider $provider): void
    {
        $this->assertBelongsToCurrentEvent($provider);
    }

    private function assertCommitteeInCurrentEvent(Committee $committee): void
    {
        $this->assertBelongsToCurrentEvent($committee);
    }

    private function assertScheduleItemInCurrentEvent(ScheduleItem $item): void
    {
        $this->assertBelongsToCurrentEvent($item);
    }

    /** CommitteeMember has no event_id column directly — check via its parent committee. */
    private function assertCommitteeMemberInCurrentEvent(CommitteeMember $member): void
    {
        $event = app('currentEvent');

        abort_unless($event && (int) $member->committee->event_id === (int) $event->id, 404);
    }
}
