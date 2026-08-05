<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_income_statement_expenses', function (Blueprint $table) {
            // Which side of Gross Profit this line sits on, snapshotted so a later
            // transaction-type flag change doesn't reclassify a closed statement.
            $table->string('section')->default('opex')->after('source'); // cost_of_sales | opex
        });

        // Existing computed cost-of-sales sources belong above the line.
        DB::table('finance_income_statement_expenses')
            ->whereIn('source', ['ad_spent', 'shipping_fee', 'cod_fee', 'vat'])
            ->update(['section' => 'cost_of_sales']);
    }

    public function down(): void
    {
        Schema::table('finance_income_statement_expenses', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};
