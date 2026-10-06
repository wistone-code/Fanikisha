<?php

use App\Services\CardCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->string('card_code', 8)->nullable();
            $table->unique(['event_id', 'card_code']);
        });

        // Give every existing pledge a code (new ones get one automatically when created).
        $used = [];
        DB::table('pledges')->whereNull('card_code')->orderBy('id')->select('id', 'event_id')->chunkById(500, function ($rows) use (&$used) {
            foreach ($rows as $row) {
                $taken = $used[$row->event_id] ??= DB::table('pledges')->where('event_id', $row->event_id)->whereNotNull('card_code')->pluck('card_code')->flip()->all();

                do {
                    $code = CardCodeService::randomCode();
                } while (isset($taken[$code]));

                $used[$row->event_id][$code] = true;
                DB::table('pledges')->where('id', $row->id)->update(['card_code' => $code]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'card_code']);
            $table->dropColumn('card_code');
        });
    }
};
