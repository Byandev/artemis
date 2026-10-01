<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            // Meta's purchase count. Cost-per-purchase is a ratio, and a ratio
            // cannot be re-blended over a date range from the ratio alone — the
            // count is the ingredient the Total/Average rows need.
            $table->unsignedInteger('ad_purchases')->nullable()->after('ad_sales');
        });
    }

    public function down(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->dropColumn('ad_purchases');
        });
    }
};
