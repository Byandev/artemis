<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_daily_sales_orders', function (Blueprint $table) {
            // Customer / address fields added to the n8n webhook payload.
            $table->string('customer_name')->nullable()->after('upsell_by');
            $table->text('address')->nullable()->after('customer_name');
            $table->string('province')->nullable()->after('address');
            $table->string('city')->nullable()->after('province');
            $table->string('brgy')->nullable()->after('city');

            // Courier + pricing/payment fields added to the payload.
            $table->string('courier')->nullable()->after('tracking_number');
            $table->string('mop')->nullable()->after('order_status');
            $table->decimal('price_final', 12, 2)->nullable()->after('total_qty');
            $table->decimal('price_initial', 12, 2)->nullable()->after('price_final');
            $table->decimal('shipping_fee', 12, 2)->nullable()->after('price_initial');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_daily_sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'address',
                'province',
                'city',
                'brgy',
                'courier',
                'mop',
                'price_final',
                'price_initial',
                'shipping_fee',
            ]);
        });
    }
};
