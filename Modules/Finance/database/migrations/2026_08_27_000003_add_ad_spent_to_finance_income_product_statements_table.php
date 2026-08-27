<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What was spent advertising this product in the month, from the Ad Spent
     * transaction shares tagged to it.
     *
     * Only tagged shares count. Ad spend that nobody attributed to a product
     * isn't guessed at here — it stays out of the per-product figures rather
     * than being spread around on an assumption.
     */
    public function up(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->decimal('ad_spent', 14, 2)->default(0)->after('delivered_amount');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_product_statements', function (Blueprint $table) {
            $table->dropColumn('ad_spent');
        });
    }
};
