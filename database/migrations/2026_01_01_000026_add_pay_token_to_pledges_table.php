<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->string('pay_token')->nullable()->unique()->after('invite_token');
        });

        // Backfill: every existing pledge gets a token immediately, since (unlike
        // invite_token) pay_token must exist from creation, not just after paid in full.
        DB::table('pledges')->whereNull('pay_token')->orderBy('id')->chunkById(200, function ($pledges) {
            foreach ($pledges as $pledge) {
                DB::table('pledges')->where('id', $pledge->id)->update(['pay_token' => Str::random(32)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn('pay_token');
        });
    }
};
