<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Wrong passwords in a row. Reset by a correct sign-in; at the limit the account is locked until a System Admin unlocks it.
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'locked_at']);
        });
    }
};
