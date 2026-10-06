<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Numbers that asked to stop. Checked before every SMS / WhatsApp we send, for every event.
        Schema::create('message_optouts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();     // digits only, international format
            $table->string('source', 20);              // card | request | organiser | admin
            $table->unsignedBigInteger('event_id')->nullable();
            $table->timestamps();
        });

        // Requests made on the public /data-request page (access, correction, deletion, stop messages…).
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('details')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('message_optouts');
    }
};
