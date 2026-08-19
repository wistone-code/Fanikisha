<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->boolean('is_super_user')->default(false);
            $table->boolean('must_change_password')->default(true);

            // Who created this account: the System Admin, or the event admin who added
            // them as a team member. Nullable because the very first seeded super user
            // has no creator. Null-on-delete so removing the creator never cascades.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->rememberToken();
            $table->timestamps();

            $table->index('is_super_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
