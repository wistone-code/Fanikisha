<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            // A short random code so the feedback link stays short enough for one SMS (fanikisha.app/f/AbC12xyz).
            $table->string('feedback_code', 10)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('pledges', fn (Blueprint $table) => $table->dropColumn('feedback_code'));
    }
};
