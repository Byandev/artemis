<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the binary `is_gross_profit_deduction` flag with a nullable
     * classification of where a transaction type lands on the income statement:
     *   - cost_of_sales : deducted from Delivered to reach Gross Profit
     *   - opex          : deducted from Gross Profit to reach Net Profit
     *   - null          : excluded — not shown on the income statement at all
     */
    public function up(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->enum('income_statement_section', ['cost_of_sales', 'opex'])
                ->nullable()
                ->default('opex')
                ->after('is_gross_profit_deduction');
        });

        // Carry the old flag forward: flagged types were cost of sales, the rest OPEX.
        DB::table('finance_transaction_types')
            ->where('is_gross_profit_deduction', true)
            ->update(['income_statement_section' => 'cost_of_sales']);

        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->dropColumn('is_gross_profit_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->boolean('is_gross_profit_deduction')->default(false)->after('name');
        });

        DB::table('finance_transaction_types')
            ->where('income_statement_section', 'cost_of_sales')
            ->update(['is_gross_profit_deduction' => true]);

        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->dropColumn('income_statement_section');
        });
    }
};
