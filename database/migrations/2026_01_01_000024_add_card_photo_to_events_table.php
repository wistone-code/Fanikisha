<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL (production) needs a true LONGBLOB (4GB max) so a real photo
        // doesn't get silently truncated at BLOB's 64KB limit. SQLite (used only
        // by the test suite) has no such size distinction, so a plain binary
        // column there works fine and avoids SQLite's grammar not knowing the
        // "longBlob" type.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('events', function (Blueprint $table) {
                $table->binary('card_photo')->nullable()->after('sms_sent_count');
                $table->string('card_photo_mime')->nullable()->after('card_photo');
            });
        } else {
            Schema::table('events', function (Blueprint $table) {
                $table->addColumn('longBlob', 'card_photo')->nullable()->after('sms_sent_count');
                $table->string('card_photo_mime')->nullable()->after('card_photo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['card_photo', 'card_photo_mime']);
        });
    }
};
