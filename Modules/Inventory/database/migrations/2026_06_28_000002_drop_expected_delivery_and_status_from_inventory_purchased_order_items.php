<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_purchased_order_items', function (Blueprint $table) {
            $table->dropColumn(['expected_delivery_date', 'delivery_status']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_purchased_order_items', function (Blueprint $table) {
            $table->date('expected_delivery_date')->nullable()->after('total_amount');
            $table->string('delivery_status')->nullable()->after('expected_delivery_date');
        });
    }
};
