<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A transaction can now be charged to several products, each bearing a share
     * of the amount, so the single `product` tag column becomes a pivot carrying
     * that share. Existing rows move over whole (one product, the full amount);
     * the now-redundant `product` column is dropped in the next migration.
     */
    public function up(): void
    {
        Schema::create('finance_transaction_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('finance_transactions')->cascadeOnDelete();
            // A normalized order_details product name (see GencysDailySalesOrder).
            $table->string('product', 191);
            // This product's share of the transaction amount. The shares of a
            // transaction always add up to its amount.
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['transaction_id', 'product']);
            $table->index('product');
        });

        if (Schema::hasColumn('finance_transactions', 'product')) {
            DB::statement("
                INSERT INTO finance_transaction_products (transaction_id, product, amount, created_at, updated_at)
                SELECT id, product, amount, NOW(), NOW()
                FROM finance_transactions
                WHERE product IS NOT NULL AND product <> ''
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transaction_products');
    }
};
