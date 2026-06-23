<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_purchased_order_items', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_purchased_order_items', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
