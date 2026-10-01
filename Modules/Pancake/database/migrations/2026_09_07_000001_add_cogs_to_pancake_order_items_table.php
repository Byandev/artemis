<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the goods on an order line cost.
     *
     * It sits on the item rather than on the order because that is the grain
     * that knows the price: the sync reads it from the variation the line sold
     * (`variation_info.last_imported_price`) and multiplies by the quantity.
     * An order's cost is the sum of its lines, worked out by whoever asks —
     * nothing is kept on the order to drift out of step.
     *
     * `cogs` is what the whole line cost, not the price of one unit. Nullable,
     * and guarded like the shipping_fee column on the orders table: null means
     * "no cost recorded", which is not the same figure as a cost of zero.
     */
    public function up(): void
    {
        if (Schema::hasColumn('pancake_order_items', 'cogs')) {
            return;
        }

        Schema::table('pancake_order_items', function (Blueprint $table) {
            $table->decimal('cogs', 12, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pancake_order_items', 'cogs')) {
            return;
        }

        Schema::table('pancake_order_items', function (Blueprint $table) {
            $table->dropColumn('cogs');
        });
    }
};
