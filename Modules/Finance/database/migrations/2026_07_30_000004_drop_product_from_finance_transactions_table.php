<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `product` tag column has been superseded by the
     * finance_transaction_products pivot (see the preceding migration, which
     * copied its values over). Drop it now that the pivot is the source of truth.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('finance_transactions', 'product')) {
            return;
        }

        Schema::table('finance_transactions', function (Blueprint $table) {
            // The workspace_id foreign key leans on the composite
            // (workspace_id, product) index; give it a standalone index to fall
            // back on before the composite (and the column) are dropped.
            $table->index('workspace_id', 'finance_transactions_workspace_id_index');
            $table->dropIndex('finance_txn_ws_product_idx');
            $table->dropColumn('product');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('finance_transactions', 'product')) {
            return;
        }

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('product')->nullable()->after('department');
            // Restore the composite index (which again covers the FK), then drop
            // the standalone workspace_id index up() added.
            $table->index(['workspace_id', 'product'], 'finance_txn_ws_product_idx');
            $table->dropIndex('finance_transactions_workspace_id_index');
        });

        // Only one product fits the restored column — keep the largest share.
        // The pivot still exists here (its own migration rolls back after this).
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
};
