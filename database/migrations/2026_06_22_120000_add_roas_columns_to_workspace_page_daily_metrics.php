<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            // Page ROAS tracker values, saved alongside the daily rollup.
            // ad_spend: Meta-reported spend rolled up to the page for the day.
            // tracked_orders / tracked_sales: the tracker's default ("confirmed")
            // orders and sales, so ROAS = tracked_sales / ad_spend is derivable.
            $table->decimal('ad_spend', 14, 2)->default(0)->after('returned_amount');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            $table->dropColumn(['ad_spend']);
        });
    }
};
