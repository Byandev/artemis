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
 * total_orders is always >= 1: orders whose customer had no prior history are not
 * rolled up, because that bucket cannot be read as the new-customer count anyway
 * (see PageOrderReportBreakdownBuilder). So this table counts orders from
 * returning customers only, never the day's full order volume.
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
