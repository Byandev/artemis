<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A month that ends in the red doesn't stop being in the red on the first
     * of the next one, so the deficit is carried into it and the month after
     * that only shows a profit once the hole is filled.
     *
     * `loss_brought_forward_*` is what the previous month left owing, held as a
     * positive amount to subtract — nought when it ended in profit, so the line
     * simply doesn't apply. `cumulative_profit_*` is net profit less that, and
     * is itself what the next month carries: the deficit therefore chains
     * across a run of bad months rather than only ever reaching back one.
     *
     * Both cost-of-goods bases get their own pair, like every other figure on
     * the statement — a month can be in profit on one basis and in the red on
     * the other, and they must not borrow each other's history.
     */
    public function up(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->decimal('loss_brought_forward_delivered_cogs', 14, 2)->default(0)
                ->after('net_profit_delivered_cogs');
            $table->decimal('cumulative_profit_delivered_cogs', 14, 2)->default(0)
                ->after('loss_brought_forward_delivered_cogs');

            $table->decimal('loss_brought_forward_bought_cogs', 14, 2)->default(0)
                ->after('net_profit_bought_cogs');
            $table->decimal('cumulative_profit_bought_cogs', 14, 2)->default(0)
                ->after('loss_brought_forward_bought_cogs');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn([
                'loss_brought_forward_delivered_cogs',
                'cumulative_profit_delivered_cogs',
                'loss_brought_forward_bought_cogs',
                'cumulative_profit_bought_cogs',
            ]);
        });
    }
};
