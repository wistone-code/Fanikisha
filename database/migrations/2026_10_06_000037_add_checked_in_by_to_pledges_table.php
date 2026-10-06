<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            // Which user's scanner/phone recorded the check-in. Plain nullable id (no foreign key)
            // so removing a staff account later never blocks or erases arrival history.
            $table->unsignedBigInteger('checked_in_by')->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn('checked_in_by');
        });
    }
};
