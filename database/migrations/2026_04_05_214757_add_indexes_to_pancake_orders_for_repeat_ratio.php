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
        Schema::table('pancake_orders', function (Blueprint $table) {
            // Covers window scans: WHERE workspace_id + confirmed_at range + customer_id/status filters
            $table->index(
                ['workspace_id', 'confirmed_at', 'customer_id', 'status'],
                'idx_orders_workspace_confirmed_customer_status'
            );

            // Covers the repeat-customer subquery: GROUP BY customer_id with workspace/status/confirmed_at filters
            $table->index(
                ['workspace_id', 'customer_id', 'status', 'confirmed_at'],
                'idx_orders_workspace_customer_status_confirmed'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_workspace_confirmed_customer_status');
            $table->dropIndex('idx_orders_workspace_customer_status_confirmed');
        });
    }
};
