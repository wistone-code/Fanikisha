<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // What sales agreed for this customer: full | sms | ecard (see config/packages.php).
            $table->string('package', 20)->default('full')->after('phone');
        });

        Schema::table('events', function (Blueprint $table) {
            // Copied from the account when the event is created; the event is what features are checked against.
            $table->string('package', 20)->default('full')->after('mode');
        });

        // Existing e-card-only accounts become the E-card package; everyone else stays Full.
        DB::table('events')->where('mode', 'ecard')->update(['package' => 'ecard']);

        $ecardOwners = DB::table('event_members')
            ->join('events', 'events.id', '=', 'event_members.event_id')
            ->where('events.mode', 'ecard')
            ->where('event_members.role', 'admin')
            ->pluck('event_members.user_id');
        DB::table('users')->whereIn('id', $ecardOwners)->update(['package' => 'ecard']);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('package'));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('package'));
    }
};
