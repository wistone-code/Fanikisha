<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Null = unlimited (no cap set by the System Admin yet).
            $table->unsignedInteger('sms_quota')->nullable()->after('reminder_auto_last_sent_at');
            $table->unsignedInteger('sms_sent_count')->default(0)->after('sms_quota');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['sms_quota', 'sms_sent_count']);
        });
    }
};
