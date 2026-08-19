<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title'); // e.g. "Vows", "Reception", "Cake cutting"
            $table->date('date');
            $table->time('time')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'date', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_items');
    }
};
