<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Needed to deliver password-reset codes via SMS instead of showing them
            // on-screen. Nullable since existing accounts won't have one yet — until
            // an admin adds their phone (Account settings / Edit account), password
            // recovery for that account isn't available and shows a clear message
            // saying so, rather than silently failing.
            $table->string('phone')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
