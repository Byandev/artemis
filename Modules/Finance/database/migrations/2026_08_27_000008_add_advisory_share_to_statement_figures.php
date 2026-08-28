<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The advisory share taken on gross profit, struck on both cost-of-goods
     * bases so each sits beside the margin it comes out of.
     *
     * Gencys-partner workspaces only, and only on a positive gross profit — a
     * loss-making month or product owes nothing rather than earning a rebate.
     * The rate is the one snapshotted on the parent statement.
     *
     * `finance_income_statements` already carries an `advisory_share` from the
     * older OPEX ledger, struck against that ledger's own gross profit. It is
     * left alone; these two are the figures the new statements use.
     */
    public function up(): void
    {
        foreach ([
            'finance_income_statements',
            'finance_income_user_statements',
            'finance_income_product_statements',
        ] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->decimal('gross_profit_delivered_cogs_advisory_share', 14, 2)
                    ->default(0)->after('gross_profit_delivered_cogs');
                $table->decimal('gross_profit_bought_cogs_advisory_share', 14, 2)
                    ->default(0)->after('gross_profit_bought_cogs');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'finance_income_statements',
            'finance_income_user_statements',
            'finance_income_product_statements',
        ] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn([
                    'gross_profit_delivered_cogs_advisory_share',
                    'gross_profit_bought_cogs_advisory_share',
                ]);
            });
        }
    }
};
