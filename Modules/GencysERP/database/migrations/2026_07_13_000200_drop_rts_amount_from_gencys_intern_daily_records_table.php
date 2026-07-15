<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // rts_amount duplicated returned_amount — preserve any existing values by
        // folding them into returned_amount before dropping the column.
        DB::table('gencys_intern_daily_records')
            ->whereNull('returned_amount')
            ->whereNotNull('rts_amount')
            ->update(['returned_amount' => DB::raw('rts_amount')]);

        Schema::table('gencys_intern_daily_records', function (Blueprint $table) {
            $table->dropColumn('rts_amount');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_intern_daily_records', function (Blueprint $table) {
            $table->decimal('rts_amount', 15, 2)->nullable()->after('rts_rate');
        });
    }
};
