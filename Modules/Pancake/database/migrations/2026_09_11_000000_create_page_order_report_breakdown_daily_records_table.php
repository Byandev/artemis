<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_order_report_breakdown_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Denormalised off the order so the page / shop / team filters still
            // apply to the rollup. Not constrained: pancake_orders.page_id is
            // nullable (Webcake orders arrive without a page).
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('shop_id');

            // The day the order was confirmed (pancake_orders.confirmed_at).
            // Cancelled/removed orders (status 6, 7) never make it here, and an
            // order that was never confirmed has no day to land on at all.
            $table->date('date');

            // The customer's order history as Pancake reported it at the moment
            // the order was created — the 'initial' phone-number report, written
            // once and never overwritten. These three are the *bucket*, not
            // measures: one row per distinct (fail, success) pair seen that day.
            // total_orders is order_fail + order_success, stored so callers can
            // filter and sort on history depth without recomputing it.
            //
            // total_orders = 0 is the day's orders with no usable history at all,
            // collapsed into one row per page: the customer's number was either
            // unknown to Pancake or known with nothing on it. So any aggregate
            // over the history columns wants total_orders >= 1 first.
            $table->unsignedInteger('order_fail')->default(0);
            $table->unsignedInteger('order_success')->default(0);
            $table->unsignedInteger('total_orders')->default(0);

            // How many of the day's orders landed in this bucket — the COUNT(*)
            // of the base query. Named orders_count rather than `count` so queries
            // against this table never have to quote a keyword.
            $table->unsignedInteger('orders_count')->default(0);

            $table->timestamps();

            // Short, explicit names — the generated ones for the three-column
            // indexes would run past MySQL's 64-character identifier limit.
            $table->index(['workspace_id', 'date'], 'porbd_ws_date_idx');
            $table->index(['workspace_id', 'date', 'page_id'], 'porbd_ws_date_page_idx');
            $table->index(['workspace_id', 'date', 'shop_id'], 'porbd_ws_date_shop_idx');
            $table->index(['workspace_id', 'date', 'total_orders'], 'porbd_ws_date_total_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_order_report_breakdown_daily_records');
    }
};
