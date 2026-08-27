<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The workspace statement carries the same figures as its per-product and
     * per-user slices, so the three read alike and can be checked against each
     * other.
     *
     * `total_delivered`, `delivered_orders` and `ad_spent` already existed and
     * are reused rather than joined by second columns holding the same numbers.
     * The existing `gross_profit`, `total_expenses`, `net_profit` and advisory
     * columns belong to the older OPEX ledger and are left alone.
     */
    public function up(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->unsignedInteger('delivered_units')->default(0)->after('delivered_orders');

            $table->unsignedInteger('shipped_orders')->default(0)->after('delivered_units');
            $table->decimal('total_shipping_fee', 14, 2)->default(0)->after('shipped_orders');

            $table->decimal('cod_fee', 14, 2)->default(0)->after('ad_spent');
            $table->decimal('cod_fee_vat', 14, 2)->default(0)->after('cod_fee');

            $table->decimal('total_bought_cogs', 14, 2)->default(0)->after('cod_fee_vat');
            $table->decimal('total_bought_cogs_delivery_fee', 14, 2)->default(0)->after('total_bought_cogs');
            $table->decimal('total_delivered_cogs', 14, 2)->default(0)->after('total_bought_cogs_delivery_fee');

            $table->decimal('gross_profit_delivered_cogs', 14, 2)->default(0)->after('total_delivered_cogs');
            $table->decimal('gross_profit_bought_cogs', 14, 2)->default(0)->after('gross_profit_delivered_cogs');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_units',
                'shipped_orders',
                'total_shipping_fee',
                'cod_fee',
                'cod_fee_vat',
                'total_bought_cogs',
                'total_bought_cogs_delivery_fee',
                'total_delivered_cogs',
                'gross_profit_delivered_cogs',
                'gross_profit_bought_cogs',
            ]);
        });
    }
};
