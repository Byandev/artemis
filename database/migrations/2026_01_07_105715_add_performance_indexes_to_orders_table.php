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
        Schema::table('orders', function (Blueprint $table) {
            // Composite indexes for workspace filtering with related entities
            $table->index(['workspace_id', 'page_id'], 'idx_orders_workspace_page');
            $table->index(['workspace_id', 'shop_id'], 'idx_orders_workspace_shop');
            $table->index(['workspace_id', 'status'], 'idx_orders_workspace_status');
            $table->index(['workspace_id', 'confirmed_at'], 'idx_orders_workspace_confirmed');

            // Date indexes for filtering and GROUP BY operations
            $table->index('confirmed_at', 'idx_orders_confirmed_at');
            $table->index('delivered_at', 'idx_orders_delivered_at');
            $table->index('created_at', 'idx_orders_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_workspace_page');
            $table->dropIndex('idx_orders_workspace_shop');
            $table->dropIndex('idx_orders_workspace_status');
            $table->dropIndex('idx_orders_workspace_confirmed');
            $table->dropIndex('idx_orders_confirmed_at');
            $table->dropIndex('idx_orders_delivered_at');
            $table->dropIndex('idx_orders_created_at');
        });
    }
};
