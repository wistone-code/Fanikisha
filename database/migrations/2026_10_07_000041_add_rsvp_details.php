<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('rsvp_plus_ones_enabled')->default(false);
            $table->unsignedTinyInteger('rsvp_max_plus_single')->default(1);
            $table->unsignedTinyInteger('rsvp_max_plus_double')->default(0);
            $table->boolean('rsvp_meal_enabled')->default(false);
            $table->text('rsvp_meal_options')->nullable(); // one option per line
            $table->boolean('rsvp_dietary_enabled')->default(false);
            $table->boolean('rsvp_message_enabled')->default(false);
            $table->date('rsvp_cutoff_date')->nullable();
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->unsignedTinyInteger('plus_ones')->default(0);
            $table->string('meal_choice', 120)->nullable();
            $table->string('dietary_note', 255)->nullable();
            $table->text('host_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['rsvp_plus_ones_enabled', 'rsvp_max_plus_single', 'rsvp_max_plus_double', 'rsvp_meal_enabled', 'rsvp_meal_options', 'rsvp_dietary_enabled', 'rsvp_message_enabled', 'rsvp_cutoff_date']);
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn(['plus_ones', 'meal_choice', 'dietary_note', 'host_message']);
        });
    }
};
