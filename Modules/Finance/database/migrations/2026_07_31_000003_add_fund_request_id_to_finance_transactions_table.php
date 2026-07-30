<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fund request a transaction settles, so a released request and the
     * ledger entry that paid it stop being two unrelated records. A loose
     * reference: several transactions may point at one request and nothing
     * reconciles their amounts against it. Nulled rather than cascaded on
     * delete — losing the request must not take the ledger entry with it.
     */
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->foreignId('fund_request_id')->nullable()->after('reference_no')
                ->constrained('finance_request_funds')->nullOnDelete();

            $table->index(['workspace_id', 'fund_request_id'], 'finance_txn_ws_fund_request_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex('finance_txn_ws_fund_request_idx');
            $table->dropConstrainedForeignId('fund_request_id');
        });
    }
};
