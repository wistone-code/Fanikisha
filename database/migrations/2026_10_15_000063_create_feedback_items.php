<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_items', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10); // host | website
            $table->string('type', 12)->default('idea'); // problem | idea | question | praise
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('message');
            $table->string('name', 120)->nullable();
            $table->string('contact', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('page', 200)->nullable();
            $table->string('status', 8)->default('new'); // new | seen | done
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_items');
    }
};
