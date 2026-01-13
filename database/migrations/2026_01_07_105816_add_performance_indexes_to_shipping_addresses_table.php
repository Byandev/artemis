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
        Schema::table('shipping_addresses', function (Blueprint $table) {
            // Foreign key index for JOIN operations (customer name sorting)
            $table->index('order_id', 'idx_shipping_addresses_order_id');

            // Composite index for geographic grouping in RTS analytics
            $table->index(['district_name', 'province_name'], 'idx_shipping_addresses_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_addresses', function (Blueprint $table) {
            $table->dropIndex('idx_shipping_addresses_order_id');
            $table->dropIndex('idx_shipping_addresses_location');
        });
    }
};
