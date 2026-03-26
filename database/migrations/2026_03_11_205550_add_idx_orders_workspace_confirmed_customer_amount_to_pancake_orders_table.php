<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->index(
                ['workspace_id', 'confirmed_at', 'customer_id', 'final_amount'],
                'idx_orders_workspace_confirmed_customer_amount'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_workspace_confirmed_customer_amount');
        });
    }
};
