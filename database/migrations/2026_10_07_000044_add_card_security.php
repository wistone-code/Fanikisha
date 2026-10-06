<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->timestamp('invite_revoked_at')->nullable();
            $table->unsignedInteger('scan_attempts')->default(0); // times an already-checked-in card was scanned again
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('checkin_confirm_name')->default(false);
        });

        // Old tokens are remembered only as a hash, so "this card is no longer valid" can be shown
        // without keeping a usable token around.
        Schema::create('card_revocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pledge_id')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('created_at')->nullable();
        });

        // One row per opening of a card page, with hashed hints only, pruned after 30 days.
        Schema::create('card_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pledge_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 16);
            $table->timestamp('created_at')->nullable();
            $table->index(['pledge_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_views');
        Schema::dropIfExists('card_revocations');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('checkin_confirm_name');
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn(['invite_revoked_at', 'scan_attempts']);
        });
    }
};
