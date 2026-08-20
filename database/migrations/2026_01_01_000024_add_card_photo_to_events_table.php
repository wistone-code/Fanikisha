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
            // Creates as a standard BLOB first (works on every grammar) —
            // upgraded to LONGBLOB below on MySQL only, via raw SQL, since
            // Blueprint's longBlob type isn't available in this Laravel version.
            $table->binary('card_photo')->nullable()->after('sms_sent_count');
            $table->string('card_photo_mime')->nullable()->after('card_photo');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE events MODIFY card_photo LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['card_photo', 'card_photo_mime']);
        });
    }
};
