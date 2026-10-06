<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->timestamp('invite_sent_at')->nullable();
            $table->string('invite_channel', 12)->nullable(); // sms | whatsapp | manual
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('unopened_reminded_at')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('auto_remind_unopened')->default(false);
            $table->unsignedSmallInteger('auto_remind_unopened_days')->default(3); // days before the event
            $table->text('unopened_reminder_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn(['invite_sent_at', 'invite_channel', 'first_opened_at', 'last_opened_at', 'open_count', 'unopened_reminded_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['auto_remind_unopened', 'auto_remind_unopened_days', 'unopened_reminder_message']);
        });
    }
};
