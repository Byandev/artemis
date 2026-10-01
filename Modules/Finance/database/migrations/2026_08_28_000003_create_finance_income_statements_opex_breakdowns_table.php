<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The statement's OPEX broken down by transaction type — one row per type
     * that had outflow in the month, together summing to `opex` on the header.
     *
     * Rebuilt whenever the statement is saved or regenerated, like the other
     * snapshots.
     *
     * Index and key names are given explicitly and kept short — the generated
     * ones run well past MySQL's 64-character limit on a table name this long.
     */
    public function up(): void
    {
        Schema::create('finance_income_statements_opex_breakdowns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('income_statement_id')
                ->constrained('finance_income_statements', indexName: 'fisob_statement')
                ->cascadeOnDelete();

            $table->foreignId('transaction_type_id')
                ->constrained('finance_transaction_types', indexName: 'fisob_type')
                ->cascadeOnDelete();

            $table->decimal('amount', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['income_statement_id', 'transaction_type_id'], 'fisob_statement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_statements_opex_breakdowns');
    }
};
