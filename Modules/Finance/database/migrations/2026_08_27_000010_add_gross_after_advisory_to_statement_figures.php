<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What is left of gross profit once the advisory share is taken, on each
     * cost-of-goods basis.
     *
     * On the workspace statement the share deducted is the lower of the two
     * bases — a percentage of gross profit, or a percentage of delivered — so
     * `gross_profit_*_advisory_share` holds whichever actually applies and this
     * is simply the gross less that. The per-user and per-product slices have
     * only the gross-profit basis, so there is nothing to choose between.
     */
    public function up(): void
    {
        foreach ([
            'finance_income_statements',
            'finance_income_user_statements',
            'finance_income_product_statements',
        ] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->decimal('gross_profit_delivered_cogs_after_advisory_share', 14, 2)
                    ->default(0)->after('gross_profit_delivered_cogs_advisory_share');
                $table->decimal('gross_profit_bought_cogs_after_advisory_share', 14, 2)
                    ->default(0)->after('gross_profit_bought_cogs_advisory_share');
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
                    'gross_profit_delivered_cogs_after_advisory_share',
                    'gross_profit_bought_cogs_after_advisory_share',
                ]);
            });
        }
    }
};
