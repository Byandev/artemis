<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger's own read path: scoped to a workspace, ordered by date then
     * position. The existing indexes cover (account_id, date, position) and
     * workspace_id alone, so an unfiltered list had to filesort every one of the
     * workspace's rows to return a single page. This one is read backwards to
     * serve `date DESC, position DESC` straight from the index.
     */
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->index(['workspace_id', 'date', 'position'], 'finance_txn_ws_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex('finance_txn_ws_sort_idx');
        });
    }
};
