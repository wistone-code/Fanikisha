<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->string('rsvp_status')->nullable()->after('checked_in_at'); // 'attending' | 'not_attending' | null (not yet responded)
            $table->timestamp('rsvp_at')->nullable()->after('rsvp_status');
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn(['rsvp_status', 'rsvp_at']);
        });
    }
};
