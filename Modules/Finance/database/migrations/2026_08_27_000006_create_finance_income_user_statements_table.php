<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-user slice of an income statement, rebuilt in the same shape as
     * `finance_income_product_statements` — one row per intern for the month,
     * carrying the same figures through to gross profit.
     *
     * This replaces `finance_user_income_statements`, which held a different
     * shape (a cost-of-sales/OPEX/net-profit ledger with its line items in a
     * JSON column). Both were snapshots rebuilt from source on save, so the old
     * table is dropped rather than migrated across.
     *
     * Index and key names are given explicitly and kept short — the generated
     * ones run past MySQL's 64-character limit on a table name this long.
     */
    public function up(): void
    {
        Schema::dropIfExists('finance_user_income_statements');

        Schema::create('finance_income_user_statements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('income_statement_id')
                ->constrained('finance_income_statements', indexName: 'fius_statement')
                ->cascadeOnDelete();

            // Null = orders whose intern cell resolves to nobody.
            $table->foreignId('user_id')->nullable()
                ->constrained('users', indexName: 'fius_user')
                ->nullOnDelete();

            // Snapshotted so a renamed or deleted user still reads back.
            $table->string('user_name')->nullable();

            $table->unsignedInteger('delivered_orders')->default(0);
            $table->unsignedInteger('delivered_units')->default(0);
            $table->decimal('delivered_amount', 14, 2)->default(0);

            $table->unsignedInteger('shipped_orders')->default(0);
            $table->decimal('total_shipping_fee', 14, 2)->default(0);

            $table->decimal('ad_spent', 14, 2)->default(0);
            $table->decimal('cod_fee', 14, 2)->default(0);
            $table->decimal('cod_fee_vat', 14, 2)->default(0);

            $table->decimal('total_bought_cogs', 14, 2)->default(0);
            $table->decimal('total_bought_cogs_delivery_fee', 14, 2)->default(0);
            $table->decimal('total_delivered_cogs', 14, 2)->default(0);

            $table->decimal('gross_profit_delivered_cogs', 14, 2)->default(0);
            $table->decimal('gross_profit_bought_cogs', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['income_statement_id', 'user_id'], 'fius_statement_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_user_statements');
    }
};
