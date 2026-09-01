<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The month's operating expenses: outflow on transaction types marked OPEX
     * on the income statement.
     *
     * Its own column rather than reusing `total_expenses`, which the older
     * ledger writes as cost of sales plus OPEX and means something different.
     */
    public function up(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->decimal('opex', 14, 2)->default(0)->after('total_expenses');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn('opex');
        });
    }
};
