<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');        // vendor / company name
            $table->string('service');     // e.g. "Catering", "Photography"
            $table->decimal('budget', 12, 2)->default(0);
            $table->string('phone')->nullable(); // normalized to +255, see PhoneNumberService
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
