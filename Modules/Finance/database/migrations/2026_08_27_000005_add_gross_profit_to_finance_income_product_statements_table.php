<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gross profit on the product for the month, struck two ways.
     *
     * Both start from delivered revenue and take off ad spend, shipping, the
     * COD fee and its VAT. They differ only in which cost of goods they charge:
     * `gross_profit_delivered_cogs` uses the cost of what actually shipped,
     * `gross_profit_bought_cogs` uses what was purchased into stock this month
     * plus the freight on it.
     *
     * The delivered figure is the truer margin on the month's sales; the bought
     * figure shows what the month cost in cash while stock was being built.
     * Neither is wrong, so both are kept rather than picking one.
     */
    public function up(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->decimal('gross_profit_delivered_cogs', 14, 2)->default(0)->after('total_delivered_cogs');
            $table->decimal('gross_profit_bought_cogs', 14, 2)->default(0)->after('gross_profit_delivered_cogs');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->dropColumn(['gross_profit_delivered_cogs', 'gross_profit_bought_cogs']);
        });
    }
};
