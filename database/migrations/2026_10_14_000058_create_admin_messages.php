<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('body', 300);
            $table->boolean('sent_sms')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'dismissed_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            // The event-day best wishes were dismissed on this date (they show again only on a new date).
            $table->date('wishes_dismissed_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('wishes_dismissed_on'));
        Schema::dropIfExists('admin_messages');
    }
};
