<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('reminder_auto_enabled')->default(false)->after('schedule_message');
            $table->unsignedSmallInteger('reminder_auto_frequency_days')->default(3)->after('reminder_auto_enabled');
            $table->time('reminder_auto_time')->default('09:00:00')->after('reminder_auto_frequency_days');
            $table->timestamp('reminder_auto_last_sent_at')->nullable()->after('reminder_auto_time');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_auto_enabled',
                'reminder_auto_frequency_days',
                'reminder_auto_time',
                'reminder_auto_last_sent_at',
            ]);
        });
    }
};
