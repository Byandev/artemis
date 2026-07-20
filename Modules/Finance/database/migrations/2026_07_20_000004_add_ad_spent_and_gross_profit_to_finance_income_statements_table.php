<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            // Gross Profit = Delivered − (Ad Spent + Shipping Fee + COD Fee + VAT).
            // Net Profit   = Gross Profit − OPEX (the transaction breakdown).
            $table->decimal('ad_spent', 15, 2)->default(0)->after('delivered_orders');
            $table->decimal('gross_profit', 15, 2)->default(0)->after('total_expenses');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn(['ad_spent', 'gross_profit']);
        });
    }
};
