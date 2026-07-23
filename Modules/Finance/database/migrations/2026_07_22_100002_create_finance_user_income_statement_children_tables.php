<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-product gross P&L rows within a user statement. Cost is fully
        // attributable: order-derived (cogs/shipping/cod/vat) plus transactions
        // tagged to this product and charged to the intern (tagged_expense).
        Schema::create('finance_user_income_statement_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_income_statement_id');
            $table->foreign('user_income_statement_id', 'fuisp_stmt_fk')
                ->references('id')->on('finance_user_income_statements')->cascadeOnDelete();

            $table->string('product', 191);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->decimal('cogs', 15, 2)->default(0);
            $table->decimal('shipping', 15, 2)->default(0);
            $table->decimal('cod', 15, 2)->default(0);
            $table->decimal('vat', 15, 2)->default(0);
            $table->decimal('tagged_expense', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->timestamps();

            $table->index('user_income_statement_id', 'fuisp_stmt_idx');
        });

        // User-level OPEX: the intern's charged transactions that are NOT tagged to
        // a product, bucketed by transaction type (key 0 = Uncategorized).
        Schema::create('finance_user_income_statement_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_income_statement_id');
            $table->foreign('user_income_statement_id', 'fuise_stmt_fk')
                ->references('id')->on('finance_user_income_statements')->cascadeOnDelete();

            $table->unsignedBigInteger('transaction_type_id')->nullable();
            $table->foreign('transaction_type_id', 'fuise_txntype_fk')
                ->references('id')->on('finance_transaction_types')->nullOnDelete();

            $table->string('type_name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index('user_income_statement_id', 'fuise_stmt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_user_income_statement_expenses');
        Schema::dropIfExists('finance_user_income_statement_products');
    }
};
