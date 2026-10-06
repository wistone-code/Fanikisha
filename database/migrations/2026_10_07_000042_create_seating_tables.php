<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('seating_mode', 10)->default('none'); // none | zone | table | row
            $table->boolean('seating_published')->default(false);
        });

        Schema::create('seating_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('seating_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seating_area_id')->nullable()->constrained('seating_areas')->nullOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('capacity')->default(8);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->string('group_name', 80)->nullable();
            $table->foreignId('seating_table_id')->nullable()->constrained('seating_tables')->nullOnDelete();
            $table->foreignId('seating_area_id')->nullable()->constrained('seating_areas')->nullOnDelete();
            $table->unsignedSmallInteger('seat_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seating_table_id');
            $table->dropConstrainedForeignId('seating_area_id');
            $table->dropColumn(['group_name', 'seat_number']);
        });

        Schema::dropIfExists('seating_tables');
        Schema::dropIfExists('seating_areas');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['seating_mode', 'seating_published']);
        });
    }
};
