<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_income_statement_expenses', function (Blueprint $table) {
            // Distinguishes computed lines (shipping_fee, cod_fee, vat) from the
            // finance-transaction-type buckets. Existing rows are transaction types.
            $table->string('source')->default('transaction_type')->after('income_statement_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statement_expenses', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
