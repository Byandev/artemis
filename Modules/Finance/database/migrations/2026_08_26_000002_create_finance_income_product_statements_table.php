<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The workspace-wide per-product slice of an income statement — one row per
     * product for the month, covering every intern, alongside the per-user
     * slices in `finance_user_income_statements`.
     *
     * Goods bought and goods delivered are tracked separately: a month's bulk
     * purchase (`total_bought_cogs`, plus the freight on it) is not the cost of
     * what actually went out the door (`total_delivered_cogs`, summed from the
     * orders themselves).
     *
     * Index and key names are given explicitly and kept short — the generated
     * ones run past MySQL's 64-character limit on a table name this long.
     */
    public function up(): void
    {
        Schema::create('finance_income_product_statements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('income_statement_id')
                ->constrained('finance_income_statements', indexName: 'fips_statement')
                ->cascadeOnDelete();

            // Null = delivered orders whose unit codes resolve to no product.
            $table->foreignId('product_id')->nullable()
                ->constrained('products', indexName: 'fips_product')
                ->nullOnDelete();

            // Snapshotted so a renamed or deleted product still reads back.
            $table->string('product_name')->nullable();

            $table->unsignedInteger('delivered_count')->default(0);
            $table->decimal('delivered_amount', 14, 2)->default(0);

            // Bought this month: the bulk goods purchase and its freight.
            $table->decimal('total_bought_cogs', 14, 2)->default(0);
            $table->decimal('total_bought_cogs_delivery_fee', 14, 2)->default(0);

            // Delivered this month: the cost of goods that actually shipped.
            $table->decimal('total_delivered_cogs', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['income_statement_id', 'product_id'], 'fips_statement_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_product_statements');
    }
};
