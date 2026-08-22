<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Null = feature off; every pledge is treated as "single".
            $table->decimal('couple_threshold_amount', 12, 2)->nullable()->after('payout_network');
        });

        Schema::table('pledges', function (Blueprint $table) {
            // Set once at save time (create or edit) — not re-evaluated if the
            // threshold changes later, so existing pledges keep their type.
            $table->string('card_type')->default('single')->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('couple_threshold_amount');
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn('card_type');
        });
    }
};
