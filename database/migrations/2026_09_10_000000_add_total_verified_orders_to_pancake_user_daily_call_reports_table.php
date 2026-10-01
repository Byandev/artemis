<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many orders got a verification call, not how many calls were placed.
     *
     * `total_verification_called` counts calls, so an order rung three times is
     * three. Read against the orders that needed verifying, that is a coverage
     * figure sailing past 100% on repeat calls alone — two orders rung three
     * times between them reads as 150% verified. This column counts the
     * distinct orders behind those calls instead, so the two answer the two
     * different questions: how much ringing was done, and how many orders it
     * actually got through.
     *
     * Distinct within the row, which is one CSR's day on one shop. An order
     * chased across two days, or by two CSRs, is one on each of their rows and
     * two in a range that sums them — much narrower than counting every call,
     * but not a workspace-wide distinct.
     *
     * Backdated rows stay 0 until the rollup is re-run for their day, which is
     * what the sync button on the analytics page is for.
     */
    public function up(): void
    {
        Schema::table('pancake_user_daily_call_reports', function (Blueprint $table) {
            $table->unsignedInteger('total_verified_orders')
                ->default(0)
                ->after('total_verification_real_called');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_user_daily_call_reports', function (Blueprint $table) {
            $table->dropColumn('total_verified_orders');
        });
    }
};
