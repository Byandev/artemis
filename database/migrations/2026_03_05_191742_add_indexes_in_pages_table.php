<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pages indexes
        Schema::table('pages', function (Blueprint $table) {
            // (workspace_id, id) for fast workspace scoping + join support
            $table->index(['workspace_id', 'id'], 'pages_workspace_id_id_idx');
        });

        // pancake_orders indexes
        Schema::table('pancake_orders', function (Blueprint $table) {
            // join helper
            $table->index(['page_id'], 'po_page_id_idx');

            // confirmed metrics (AOV, TotalSales, TotalOrders, LTV, Repeat, TimeToFirstOrder)
            $table->index(['page_id', 'customer_id', 'confirmed_at'], 'po_page_customer_confirmed_at_idx');

            // delivery metrics (AverageDeliveryDays)
            $table->index(['page_id', 'delivered_at', 'shipped_at'], 'po_page_delivered_shipped_idx');

            // RTS metrics (returning_at filters)
            $table->index(['page_id', 'returning_at'], 'po_page_returning_at_idx');
        });

        // pancake_customers indexes
        Schema::table('pancake_customers', function (Blueprint $table) {
            // join key used in TimeToFirstOrder
            $table->unique(['customer_id'], 'pc_customer_id_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_customers', function (Blueprint $table) {
            $table->dropUnique('pc_customer_id_uq');
        });

        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('po_page_returning_at_idx');
            $table->dropIndex('po_page_delivered_shipped_idx');
            $table->dropIndex('po_page_customer_confirmed_at_idx');
            $table->dropIndex('po_page_id_idx');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_workspace_id_id_idx');
        });
    }
};
