<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split "how much went out" into the two figures that actually differ:
     * `delivered_orders` is parcels, `delivered_units` is pieces. A single
     * parcel can carry three of a product, and a product's unit count is what
     * stock is drawn down by — the parcel count is not.
     *
     * `delivered_count` was only ever the parcel count, so it is renamed rather
     * than joined by a second column holding the same number.
     */
    public function up(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->renameColumn('delivered_count', 'delivered_orders');
        });

        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->unsignedInteger('delivered_units')->default(0)->after('delivered_orders');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->dropColumn('delivered_units');
        });

        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->renameColumn('delivered_orders', 'delivered_count');
        });
    }
};
