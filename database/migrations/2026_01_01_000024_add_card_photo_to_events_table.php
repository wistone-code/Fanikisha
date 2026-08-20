<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Stored in the database (not the filesystem) since Railway's
            // filesystem is not persistent across deploys.
            $table->addColumn('longBlob', 'card_photo')->nullable()->after('sms_sent_count');
            $table->string('card_photo_mime')->nullable()->after('card_photo');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['card_photo', 'card_photo_mime']);
        });
    }
};
