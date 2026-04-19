<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('running_balance');
            $table->index(['account_id', 'date', 'position'], 'finance_txn_sort_idx');
        });

        // Backfill: assign position based on current id order within each (account_id, date) group
        DB::statement('
            UPDATE finance_transactions t
            INNER JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY account_id, date ORDER BY id) as rn
                FROM finance_transactions
            ) ranked ON t.id = ranked.id
            SET t.position = ranked.rn
        ');
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex('finance_txn_sort_idx');
            $table->dropColumn('position');
        });
    }
};
