<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pages: supports workspace + shops filters + join to orders
        Schema::table('pages', function (Blueprint $table) {
            $table->index(['workspace_id', 'shop_id', 'id'], 'pages_workspace_shop_id_id_idx');
        });

        // pancake_orders: status-aware composites (recommended for millions of rows)
        Schema::table('pancake_orders', function (Blueprint $table) {
            // Confirmed-based metrics + customer grouping (AOV, TotalSales, TotalOrders, LTV, Repeat, TimeToFirstOrder)
            $table->index(
                ['page_id', 'status', 'confirmed_at', 'customer_id'],
                'po_page_status_confirmed_customer_idx'
            );

            // Delivery metric (AverageDaysFromShippedToDelivered)
            $table->index(
                ['page_id', 'status', 'delivered_at', 'shipped_at'],
                'po_page_status_delivered_shipped_idx'
            );

            // RTS metric (returning/delivered filtering)
            $table->index(
                ['page_id', 'status', 'returning_at'],
                'po_page_status_returning_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('po_page_status_returning_idx');
            $table->dropIndex('po_page_status_delivered_shipped_idx');
            $table->dropIndex('po_page_status_confirmed_customer_idx');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_workspace_shop_id_id_idx');
        });
    }
};
