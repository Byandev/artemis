<?php

namespace Modules\Pancake\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily breakdown of orders by the customer history they arrived with.
 *
 * One row per workspace / page / shop / day / (order_fail, order_success) pair,
 * the day being when the order was confirmed and cancelled orders excluded.
 * order_fail and order_success are the customer's prior failed and successful
 * orders as Pancake reported them when the order was placed — the 'initial'
 * phone-number report, which SyncPhoneNumberReportsAction writes once and never
 * overwrites. orders_count is how many of that day's orders carried that exact
 * pair; total_orders is the pair's sum, i.e. how deep that customer's history was.
 *
 * Rows come in two kinds, told apart by total_orders:
 *
 *  - total_orders >= 1 — the customer had prior orders; order_fail and
 *                        order_success describe how those went.
 *  - total_orders = 0  — no usable history, collapsed into one row per page per
 *                        day: either Pancake had never seen the number, or it
 *                        had seen it with no orders on it. The history columns
 *                        are 0 because there is nothing to report, not because
 *                        the customer had a clean record.
 *
 * So any aggregate over the history columns wants total_orders >= 1 first, or the
 * no-history rows will dilute it with zeroes. Nothing is dropped, so summing
 * orders_count across a day gives that day's confirmed, non-cancelled orders.
 *
 * The grain is deliberately the raw pair rather than a fixed set of risk bands,
 * so any bucketing a report wants — including the 0-10 / 11-20 / … bands
 * RtsCxQuery computes live — can be derived from this table without going back
 * to pancake_orders.
 *
 * Built by `build-page-order-report-breakdown-daily-records`.
 */
class PageOrderReportBreakdownDailyRecord extends Model
{
    use ScopesToVisibleTeams;

    protected $table = 'page_order_report_breakdown_daily_records';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'order_fail' => 'integer',
        'order_success' => 'integer',
        'total_orders' => 'integer',
        'orders_count' => 'integer',
    ];

    /** shop_id is denormalised onto the row, so teams are reached the same way an Order reaches them. */
    protected function visibilityTeamRelation(): string
    {
        return 'shop.teams';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
