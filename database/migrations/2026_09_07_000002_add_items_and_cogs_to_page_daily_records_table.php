<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the day's orders were made of, so the tracker can estimate a margin
     * rather than stopping at revenue.
     *
     * Both are measured over the same orders `orders` and `sales` are — the ones
     * confirmed on that date — so they divide into each other cleanly.
     *
     * `order_cogs` is nullable while `item_quantity` is not: a day with orders
     * always sold some number of units, but its lines may carry no cost at all,
     * and "nothing recorded" must not read as "the goods were free".
     */
    public function up(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->unsignedInteger('item_quantity')->nullable()->after('orders');
            $table->decimal('order_cogs', 15, 2)->nullable()->after('item_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->dropColumn(['item_quantity', 'order_cogs']);
        });
    }
};
