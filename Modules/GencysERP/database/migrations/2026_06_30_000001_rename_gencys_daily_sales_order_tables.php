<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('gencys_daily_sales_orders', 'gencys_orders');
        Schema::rename('gencys_daily_sales_order_items', 'gencys_order_items');
    }

    public function down(): void
    {
        Schema::rename('gencys_order_items', 'gencys_daily_sales_order_items');
        Schema::rename('gencys_orders', 'gencys_daily_sales_orders');
    }
};
