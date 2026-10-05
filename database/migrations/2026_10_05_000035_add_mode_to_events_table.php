<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // 'contributions' = the full app (pledges, finances, providers…).
            // 'ecard' = e-card-only service: guest list, e-cards, RSVP and check-in.
            $table->string('mode', 20)->default('contributions')->after('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
