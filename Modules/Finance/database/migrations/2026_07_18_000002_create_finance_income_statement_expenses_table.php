<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_income_statement_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_statement_id')
                ->constrained('finance_income_statements')
                ->cascadeOnDelete();

            // The transaction type this row sums. Nullable so the "Uncategorized"
            // bucket (transactions with no type) can be stored, and so a deleted
            // type doesn't drop the row. `type_name` keeps the label regardless.
            $table->foreignId('transaction_type_id')
                ->nullable()
                ->constrained('finance_transaction_types')
                ->nullOnDelete();
            $table->string('type_name');

            // Snapshot of this type's summed expense total for the month.
            $table->decimal('amount', 15, 2)->default(0);

            $table->timestamps();

            $table->index('income_statement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_statement_expenses');
    }
};
