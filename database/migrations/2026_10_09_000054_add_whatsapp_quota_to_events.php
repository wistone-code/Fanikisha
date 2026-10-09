<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // WhatsApp invitations are billed per message by Meta, so unlike SMS the default is 0:
            // nothing is sent until the System Admin sets a number for the event.
            $table->unsignedInteger('whatsapp_quota')->default(0)->after('sms_sent_count');
            $table->unsignedInteger('whatsapp_sent_count')->default(0)->after('whatsapp_quota');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_quota', 'whatsapp_sent_count']);
        });
    }
};
