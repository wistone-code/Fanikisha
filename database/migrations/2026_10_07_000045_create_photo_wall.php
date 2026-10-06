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
            $table->boolean('photo_wall_enabled')->default(false);
            $table->string('photo_wall_token', 40)->nullable()->unique();
            $table->string('photo_wall_pin', 12)->nullable();
            $table->string('photo_wall_access', 10)->default('link'); // link | guests
            $table->string('photo_wall_open_mode', 12)->default('event_day'); // event_day | always
            $table->unsignedSmallInteger('photo_wall_close_days')->default(7);
            $table->unsignedSmallInteger('photo_wall_max_per_guest')->default(10);
            $table->unsignedSmallInteger('photo_wall_max_total')->default(300);
            $table->boolean('photo_wall_uploads_blocked')->default(false);
        });

        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('uploader_key', 64); // hashed cookie / guest hint, only used for the per-guest limit
            $table->string('uploader_name', 80)->nullable();
            $table->binary('thumb');
            $table->binary('image');
            $table->unsignedInteger('size')->default(0);
            $table->boolean('hidden')->default(false);
            $table->unsignedSmallInteger('reports')->default(0);
            $table->timestamps();
            $table->index(['event_id', 'hidden']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE event_photos MODIFY thumb MEDIUMBLOB NOT NULL');
            DB::statement('ALTER TABLE event_photos MODIFY image LONGBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['photo_wall_token']);
            $table->dropColumn(['photo_wall_enabled', 'photo_wall_token', 'photo_wall_pin', 'photo_wall_access', 'photo_wall_open_mode', 'photo_wall_close_days', 'photo_wall_max_per_guest', 'photo_wall_max_total', 'photo_wall_uploads_blocked']);
        });
    }
};
