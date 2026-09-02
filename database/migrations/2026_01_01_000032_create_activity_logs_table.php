<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Records account- and event-level actions for the System Admin's Logs
        // screen (e.g. account created, password reset, event created). Kept
        // deliberately separate from event content — see ActivityLog model.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Who performed the action. Null if the system did it (e.g. an
            // automated job) rather than a logged-in user.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // The account the action was about, if any (e.g. the account that
            // was created, reset, or edited).
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();

            // The event the action relates to, if any (e.g. "event created").
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 60);
            $table->string('description', 255);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_user_id', 'created_at']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
