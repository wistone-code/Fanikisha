<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->string('feedback_message', 500)->nullable();
            $table->timestamp('feedback_at')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            // When on, guests who came get a "rate the event" link in their thank-you SMS.
            $table->boolean('feedback_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('feedback_enabled'));
        Schema::table('pledges', fn (Blueprint $table) => $table->dropColumn(['feedback_rating', 'feedback_message', 'feedback_at']));
    }
};
