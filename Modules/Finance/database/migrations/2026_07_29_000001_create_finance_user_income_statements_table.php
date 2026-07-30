<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_user_income_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_statement_id')
                ->constrained('finance_income_statements')
                ->cascadeOnDelete();
            // Null user_id = the "Unassigned" catch-all row.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name');

            $table->unsignedInteger('orders')->default(0);
            $table->decimal('delivered', 15, 2)->default(0);
            $table->decimal('cost_of_sales', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->decimal('advisory', 15, 2)->default(0);
            $table->decimal('opex', 15, 2)->default(0);
            $table->decimal('net_profit', 15, 2)->default(0);

            // Rates + partner flag snapshotted so the single-user ledger renders
            // exactly as computed, independent of later settings changes.
            $table->decimal('cod_fee_rate', 8, 4)->default(0);
            $table->decimal('vat_rate', 8, 4)->default(0);
            $table->decimal('advisory_rate', 8, 4)->default(0);
            $table->boolean('gencys_partner')->default(false);

            // The per-user ledger's expense line items.
            $table->json('lines')->nullable();

            $table->timestamps();

            $table->index(['income_statement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_user_income_statements');
    }
};
