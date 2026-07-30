<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            // Optional product tag (a normalized order_details product name) so a
            // transaction can be attributed to a product on the per-product IS.
            $table->string('product')->nullable()->after('charge_to');
            $table->index(['workspace_id', 'product'], 'finance_txn_ws_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex('finance_txn_ws_product_idx');
            $table->dropColumn('product');
        });
    }
};
