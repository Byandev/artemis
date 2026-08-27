<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parcels shipped out in the month and the courier fee on them.
     *
     * Shipping is dated by `shipped_out_date`, not by when a parcel was
     * delivered, and it counts whatever the parcel's fate — you pay the courier
     * for a return too. So this is a different set of orders from the delivered
     * columns and the two counts are not expected to agree.
     */
    public function up(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->unsignedInteger('shipped_orders')->default(0)->after('ad_spent');
            $table->decimal('total_shipping_fee', 14, 2)->default(0)->after('shipped_orders');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->dropColumn(['shipped_orders', 'total_shipping_fee']);
        });
    }
};
