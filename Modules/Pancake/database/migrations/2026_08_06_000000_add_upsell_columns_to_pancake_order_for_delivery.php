<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->date('upsell_date')->nullable()->after('customer_phone');
            $table->decimal('upsell_price', 12, 2)->nullable()->after('upsell_date');
            $table->text('order_details')->nullable()->after('upsell_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropColumn(['upsell_date', 'upsell_price', 'order_details']);
        });
    }
};
