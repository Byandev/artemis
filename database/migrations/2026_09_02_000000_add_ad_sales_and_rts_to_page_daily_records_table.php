<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            // In-flight returns (returning_at set, returned_at still null) and the
            // blended RTS rate, mirroring advertiser_performance_daily_records.
            $table->decimal('returning_amount', 15, 2)->nullable()->after('returned_amount');
            $table->decimal('rts_rate', 8, 2)->nullable()->after('returning_amount');

            // Meta-attributed purchase value, so ad_roas = ad_sales / ad_spent.
            $table->decimal('ad_sales', 15, 2)->nullable()->after('ad_spent');
            $table->decimal('ad_roas', 10, 2)->nullable()->after('roas');

            // Purchases (Meta) and orders (Pancake) per unit of ad spend. These
            // land well under 1 — a few dozen purchases against thousands in
            // spend — so 2dp would round every page to 0.00. Six is enough to
            // recover the original count by multiplying back out.
            $table->decimal('ad_cpp', 14, 6)->nullable()->after('ad_roas');
            $table->decimal('cpp', 14, 6)->nullable()->after('ad_cpp');
        });
    }

    public function down(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->dropColumn([
                'returning_amount',
                'rts_rate',
                'ad_sales',
                'ad_roas',
                'ad_cpp',
                'cpp',
            ]);
        });
    }
};
