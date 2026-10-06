<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('host_names', 160)->nullable();
            $table->boolean('thank_you_enabled')->default(false);
            $table->string('thank_you_time', 5)->default('09:00'); // HH:MM, sent the morning after the event
            $table->text('thank_you_attended_message')->nullable();
            $table->text('thank_you_absent_message')->nullable();
            $table->boolean('thank_you_acknowledge_paid')->default(false);
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->timestamp('thank_you_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['host_names', 'thank_you_enabled', 'thank_you_time', 'thank_you_attended_message', 'thank_you_absent_message', 'thank_you_acknowledge_paid']);
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn('thank_you_sent_at');
        });
    }
};
