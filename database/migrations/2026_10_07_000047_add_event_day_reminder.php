<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_time', 5)->nullable(); // HH:MM
            $table->string('venue_name', 160)->nullable();
            $table->string('venue_address', 255)->nullable();
            $table->decimal('venue_lat', 10, 7)->nullable();
            $table->decimal('venue_lng', 10, 7)->nullable();
            $table->string('landmark_note_en', 200)->nullable();
            $table->string('landmark_note_sw', 200)->nullable();
            $table->boolean('event_day_reminder_enabled')->default(false);
            $table->string('event_day_reminder_time', 5)->default('07:00');
            $table->text('event_day_reminder_message')->nullable();
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->timestamp('event_day_reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['event_time', 'venue_name', 'venue_address', 'venue_lat', 'venue_lng', 'landmark_note_en', 'landmark_note_sw', 'event_day_reminder_enabled', 'event_day_reminder_time', 'event_day_reminder_message']);
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn('event_day_reminder_sent_at');
        });
    }
};
