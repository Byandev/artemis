<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {

            // speeds up most dashboard metrics
            $table->index(
                ['workspace_id', 'confirmed_at', 'status'],
                'idx_orders_workspace_confirmed_status'
            );

            // speeds up repeat order + LTV metrics
            $table->index(
                ['workspace_id', 'customer_id', 'confirmed_at'],
                'idx_orders_workspace_customer_confirmed'
            );

            // speeds up delivery metrics
            $table->index(
                ['workspace_id', 'delivered_at'],
                'idx_orders_workspace_delivered'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_workspace_confirmed_status');
            $table->dropIndex('idx_orders_workspace_customer_confirmed');
            $table->dropIndex('idx_orders_workspace_delivered');
        });
    }
};
