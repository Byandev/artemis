<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_daily_sales_order_items', function (Blueprint $table) {
            $table->id();
            // Explicit short FK name: the default would exceed MySQL's 64-char
            // identifier limit given the long table name.
            $table->foreignId('order_id')
                ->constrained('gencys_daily_sales_orders', indexName: 'gdso_items_order_id_fk')
                ->cascadeOnDelete();

            // Each line item is parsed from the raw "Order" string: the string is
            // split by comma into items, and each item is split on the first "x"
            // into a quantity and an sku (e.g. "1x2X MAGNERVE" => qty 1, sku "2X MAGNERVE").
            $table->integer('quantity')->nullable();
            $table->string('sku')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_daily_sales_order_items');
    }
};
