<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How an OPEX type's company-wide pool is split across products on the
     * per-user income statement. Shared costs aren't attributable to one
     * product, so each carries the slice matching its share of some company
     * metric — and which metric differs by cost: a CSR's salary tracks every
     * order taken, while warehouse costs track parcels actually delivered.
     *
     * Null = the default (delivered parcels). Only read for types whose
     * `income_statement_section` is `opex`.
     */
    public function up(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->string('opex_allocation_basis')->nullable()->after('income_statement_section');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->dropColumn('opex_allocation_basis');
        });
    }
};
