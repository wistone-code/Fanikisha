<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_requests', function (Blueprint $table) {
            $table->text('note')->nullable()->after('details'); // what we did, written when the request is closed
        });
    }

    public function down(): void
    {
        Schema::table('data_requests', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
