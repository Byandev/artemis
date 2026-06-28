<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            // One expected delivery date for the whole order (see PurchasedOrder timeliness).
            $table->date('expected_delivery_date')->nullable()->after('delivery_no');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            $table->dropColumn('expected_delivery_date');
        });
    }
};
