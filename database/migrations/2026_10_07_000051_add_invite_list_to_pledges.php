<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            // Someone invited who is not a contributor (family, VIPs…). Never counted in pledges or finance.
            $table->boolean('guest_only')->default(false)->after('paid');
            // The invitation list is chosen: added as a new guest or picked from the pledge list.
            $table->boolean('on_invite_list')->default(false)->after('guest_only');
        });

        // Everyone already on an event keeps showing on its invitation page.
        DB::table('pledges')->update(['on_invite_list' => true]);
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn(['guest_only', 'on_invite_list']);
        });
    }
};
