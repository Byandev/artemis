<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many deliveries the RMO calls were about, not how many calls landed.
     *
     * `total_rmo_called` counts calls, and RMO rings the same parcel more than
     * once by design — the customer, then the rider, then the customer again
     * when nobody picked up. Forty calls can be twenty parcels, and the card
     * that only shows the forty overstates how much of the day's delivery list
     * was actually reached. This column counts the distinct deliveries behind
     * those calls, so the two answer the two different questions: how much
     * ringing was done, and how many parcels it got through.
     *
     * The delivery rather than the order, because that is the unit RMO chases:
     * an order re-loaded for delivery on a later day is a second parcel to
     * chase and gets its own pancake_order_for_delivery row.
     *
     * Distinct within the row, which is one CSR's day on one shop. A parcel
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
            $table->unsignedInteger('total_rmo_orders')
                ->default(0)
                ->after('total_rmo_call_time');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_user_daily_call_reports', function (Blueprint $table) {
            $table->dropColumn('total_rmo_orders');
        });
    }
};
