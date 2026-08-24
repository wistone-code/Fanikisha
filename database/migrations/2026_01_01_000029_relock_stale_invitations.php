<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time data fix. Before this app auto re-locked an invitation whenever a
 * payment correction dropped a pledge back below fully paid, any pledge that
 * had already been unlocked and then corrected down was left in a stale,
 * inconsistent state: the "Locked / Balance due" badge (driven purely by
 * current payment status) correctly showed as locked, while the SMS/WhatsApp
 * send buttons (driven purely by invite_token being set) kept showing anyway,
 * since nothing had cleared the leftover token. This clears invite_token for
 * every pledge currently in that mismatched state, across every event, so
 * existing records match what the app would now do automatically going
 * forward. Guests who are genuinely paid in full are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pledges')
            ->whereNotNull('invite_token')
            ->where(function ($query) {
                // Mirrors Pledge::isPaidInFull() exactly: NOT paid in full means
                // either the amount is zero/negative (invalid but possible), or
                // paid hasn't caught up to amount yet.
                $query->where('amount', '<=', 0)
                    ->orWhereColumn('paid', '<', 'amount');
            })
            ->update(['invite_token' => null]);
    }

    public function down(): void
    {
        // Not reversible — the original invite_token values are gone, and
        // regenerating new ones would issue different links than guests may
        // already have been sent, which would be worse than leaving this as-is.
    }
};
