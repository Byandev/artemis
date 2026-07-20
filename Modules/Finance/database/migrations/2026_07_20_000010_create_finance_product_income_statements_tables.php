<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-product income statement (product = normalized order_details name).
        // Explicit short index/FK names — the table name is long, so auto-generated
        // identifiers would exceed MySQL's 64-char limit.
        Schema::create('finance_product_income_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('product', 191);
            $table->date('period_month');

            $table->decimal('total_delivered', 15, 2)->default(0);
            $table->unsignedInteger('delivered_orders')->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->decimal('net_profit', 15, 2)->default(0);
            $table->decimal('cod_fee_rate', 6, 4)->default(0.02);
            $table->decimal('vat_rate', 6, 4)->default(0.12);
            $table->decimal('advisory_rate', 6, 4)->default(0.30);
            $table->decimal('advisory_share', 15, 2)->default(0);
            $table->string('status')->default('final');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'product', 'period_month'], 'fpis_ws_prod_month_uq');
        });

        Schema::create('finance_product_income_statement_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_income_statement_id');
            $table->foreign('product_income_statement_id', 'fpise_stmt_fk')
                ->references('id')->on('finance_product_income_statements')->cascadeOnDelete();

            $table->string('source');
            $table->string('section')->default('opex'); // cost_of_sales | opex

            $table->unsignedBigInteger('transaction_type_id')->nullable();
            $table->foreign('transaction_type_id', 'fpise_txntype_fk')
                ->references('id')->on('finance_transaction_types')->nullOnDelete();

            $table->string('type_name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index('product_income_statement_id', 'fpise_stmt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_product_income_statement_expenses');
        Schema::dropIfExists('finance_product_income_statements');
    }
};
