<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // System Admin can pause an account without permanently deleting it and
            // its event data. A suspended account is blocked at login (see
            // LoginController) but everything about them stays intact until either
            // reactivated or explicitly deleted.
            $table->boolean('is_suspended')->default(false)->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_suspended');
        });
    }
};
