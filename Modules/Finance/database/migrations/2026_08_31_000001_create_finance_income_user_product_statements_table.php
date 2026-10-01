<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-user-per-product slice of an income statement: one row for each
     * pair of a seller and a product they moved that month.
     *
     * The two existing slices each collapse one of those dimensions — the user
     * statement adds a person's products together, the product statement adds a
     * product's sellers together — so neither can answer "how did this person do
     * on this product", which is the question a product run by several people
     * (and a person running several products) actually raises.
     *
     * Carries the same figures as its two siblings so the three pages read
     * alike. The rows reconcile down to `finance_income_product_statements`:
     * summed over users, a product's figures here equal that product's row
     * there. See UserProductIncomeStatementService for why that, and not the
     * user statement, is the total they add up to.
     *
     * Index and key names are given explicitly and kept short — the generated
     * ones run past MySQL's 64-character limit on a table name this long.
     */
    public function up(): void
    {
        Schema::create('finance_income_user_product_statements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('income_statement_id')
                ->constrained('finance_income_statements', indexName: 'fiups_statement')
                ->cascadeOnDelete();

            // Null = orders credited to nobody, or a product cost that no seller
            // moved this month (see the service's unallocated fallback).
            $table->foreignId('user_id')->nullable()
                ->constrained('users', indexName: 'fiups_user')
                ->nullOnDelete();

            // Null = delivered items whose unit codes resolve to no product.
            $table->foreignId('product_id')->nullable()
                ->constrained('products', indexName: 'fiups_product')
                ->nullOnDelete();

            // Snapshotted so a renamed or deleted record still reads back.
            $table->string('user_name')->nullable();
            $table->string('product_name')->nullable();

            $table->unsignedInteger('delivered_orders')->default(0);
            $table->unsignedInteger('delivered_units')->default(0);
            $table->decimal('delivered_amount', 14, 2)->default(0);

            $table->unsignedInteger('shipped_orders')->default(0);
            $table->decimal('total_shipping_fee', 14, 2)->default(0);

            $table->decimal('ad_spent', 14, 2)->default(0);
            $table->decimal('cod_fee', 14, 2)->default(0);
            $table->decimal('cod_fee_vat', 14, 2)->default(0);

            // Bought this month: the goods purchase and the freight on it, each
            // the seller's share of the product's total.
            $table->decimal('total_bought_cogs', 14, 2)->default(0);
            $table->decimal('total_bought_cogs_delivery_fee', 14, 2)->default(0);

            // Delivered this month: the cost of the goods that actually shipped.
            $table->decimal('total_delivered_cogs', 14, 2)->default(0);

            $table->decimal('gross_profit_delivered_cogs', 14, 2)->default(0);
            $table->decimal('gross_profit_bought_cogs', 14, 2)->default(0);
            $table->decimal('gross_profit_delivered_cogs_advisory_share', 14, 2)->default(0);
            $table->decimal('gross_profit_bought_cogs_advisory_share', 14, 2)->default(0);
            $table->decimal('gross_profit_delivered_cogs_after_advisory_share', 14, 2)->default(0);
            $table->decimal('gross_profit_bought_cogs_after_advisory_share', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['income_statement_id', 'user_id', 'product_id'], 'fiups_statement_user_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_user_product_statements');
    }
};
