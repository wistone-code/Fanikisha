<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('card_template', 20)->default('classic');
            $table->string('card_default_lang', 2)->default('en');
            $table->text('card_text_en')->nullable();
            $table->text('card_text_sw')->nullable();
            $table->string('card_video_url', 500)->nullable();
            $table->string('card_music_url', 500)->nullable();
            $table->boolean('card_has_music')->default(false);
            // The customer's own designed card (the image lives in event_assets), plus where each dynamic element sits on it.
            $table->boolean('card_has_custom_design')->default(false);
            $table->unsignedSmallInteger('custom_design_width')->nullable();
            $table->unsignedSmallInteger('custom_design_height')->nullable();
            $table->text('custom_design_layout')->nullable(); // JSON
            $table->boolean('use_custom_design')->default(false);
        });

        // Big files live in their own table so they are not loaded with every Event query
        // (Railway's disk is ephemeral, so they are kept in the database like the card photo).
        Schema::create('event_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // music | design
            $table->string('mime', 40);
            $table->binary('data');
            $table->timestamps();
            $table->unique(['event_id', 'kind']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE event_assets MODIFY data LONGBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_assets');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'card_template', 'card_default_lang', 'card_text_en', 'card_text_sw', 'card_video_url', 'card_music_url',
                'card_has_music', 'card_has_custom_design', 'custom_design_width',
                'custom_design_height', 'custom_design_layout', 'use_custom_design',
            ]);
        });
    }
};
