<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            // Filled from Meta's webhook: sent -> delivered -> read, or failed (with Meta's reason).
            $table->string('whatsapp_message_id', 120)->nullable()->index();
            $table->string('whatsapp_status', 20)->nullable();
            $table->timestamp('whatsapp_status_at')->nullable();
            $table->string('whatsapp_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_message_id']);
            $table->dropColumn(['whatsapp_message_id', 'whatsapp_status', 'whatsapp_status_at', 'whatsapp_error']);
        });
    }
};
