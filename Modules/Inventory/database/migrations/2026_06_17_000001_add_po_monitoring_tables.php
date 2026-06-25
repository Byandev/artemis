<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_purchased_order_items', function (Blueprint $table) {
            $table->date('expected_delivery_date')->nullable()->after('total_amount');
        });

        Schema::create('inventory_purchased_order_item_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_purchased_order_item_id');
            $table->foreign('inventory_purchased_order_item_id', 'ipoid_item_id_foreign')
                ->references('id')->on('inventory_purchased_order_items')->onDelete('cascade');
            $table->date('delivery_date');
            $table->string('delivery_no')->nullable();
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->index('inventory_purchased_order_item_id', 'ipoid_item_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_purchased_order_item_deliveries');

        Schema::table('inventory_purchased_order_items', function (Blueprint $table) {
            $table->dropColumn('expected_delivery_date');
        });
    }
};
