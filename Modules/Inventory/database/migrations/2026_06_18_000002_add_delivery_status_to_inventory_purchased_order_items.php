<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_purchased_order_items', function (Blueprint $table) {
            // Manual delivery status override: 'ontime' | 'delayed' | null (derive from dates).
            $table->string('delivery_status')->nullable()->after('expected_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_purchased_order_items', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });
    }
};
