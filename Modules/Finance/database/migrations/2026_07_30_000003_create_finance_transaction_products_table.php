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
     * that share. Existing rows move over whole (one product, the full amount).
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

            Schema::table('finance_transactions', function (Blueprint $table) {
                // The workspace_id foreign key leans on the composite
                // (workspace_id, product) index; give it a standalone index to
                // fall back on before the composite (and the column) are dropped.
                $table->index('workspace_id', 'finance_transactions_workspace_id_index');
                $table->dropIndex('finance_txn_ws_product_idx');
                $table->dropColumn('product');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('finance_transactions', 'product')) {
            Schema::table('finance_transactions', function (Blueprint $table) {
                $table->string('product')->nullable()->after('department');
                // Restore the composite index (which again covers the FK), then
                // drop the standalone workspace_id index up() added.
                $table->index(['workspace_id', 'product'], 'finance_txn_ws_product_idx');
                $table->dropIndex('finance_transactions_workspace_id_index');
            });

            // Only one product fits the restored column — keep the largest share.
            DB::statement('
                UPDATE finance_transactions t
                SET product = (
                    SELECT tp.product
                    FROM finance_transaction_products tp
                    WHERE tp.transaction_id = t.id
                    ORDER BY tp.amount DESC, tp.id ASC
                    LIMIT 1
                )
            ');
        }

        Schema::dropIfExists('finance_transaction_products');
    }
};
