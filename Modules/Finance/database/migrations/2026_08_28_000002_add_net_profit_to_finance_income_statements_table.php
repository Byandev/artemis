<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the month made once operating expenses are taken off, on each
     * cost-of-goods basis.
     *
     * Struck from gross profit after the advisory share, not from gross profit
     * itself — the advisory is a real deduction shown a line above, and taking
     * it off here is what makes the statement read as one continuous
     * subtraction. On a workspace with no advisory the two are the same number.
     *
     * Separate from the older `net_profit` column, which the OPEX ledger writes
     * from its own included-lines arithmetic and means something different.
     */
    public function up(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->decimal('net_profit_delivered_cogs', 14, 2)->default(0)->after('opex');
            $table->decimal('net_profit_bought_cogs', 14, 2)->default(0)->after('net_profit_delivered_cogs');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn(['net_profit_delivered_cogs', 'net_profit_bought_cogs']);
        });
    }
};
