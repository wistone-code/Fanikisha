<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->string('event_type', 30);
            $table->date('event_date')->nullable();
            $table->string('location', 150)->nullable();
            $table->unsignedInteger('guests')->nullable();
            $table->string('needs', 20)->default('ecards'); // ecards | both
            $table->string('language', 2)->default('en');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new');   // new | contacted | created | declined
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_requests');
    }
};
