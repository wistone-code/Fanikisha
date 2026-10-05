<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Optional organizer-chosen brand color (#RRGGBB). Null = use the event type's default theme.
            $table->string('theme_color', 7)->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('theme_color');
        });
    }
};
