<?php

namespace App\Console\Commands;

use App\Models\AdvertiserPerformanceDailyRecord;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\Insight;
use Modules\Pancake\Models\Order;

/**
 * Build Artemis-source advertiser performance rows, one per app user per day,
 * combining:
 *   - Meta Ads:   ad spend + purchase value (= sales), attributed via
 *                 ad set → meta_page_id → page → owner_id (the user).
 *   - Pancake POS: orders + returning/delivered/RTS, via pancake_users.user_id.
 *
 * Only non-Gencys-partner workspaces (they default to the Artemis source).
 * Writes to the unified advertiser_performance_daily_records table
 * (source=artemis, advertiser=User). Re-runnable — upserts on the natural key.
 */
class BuildDailyAdvertiserPerformanceCommand extends Command
{
    protected $signature = 'build-advertiser-daily-performance
        {--date= : Build a single date (YYYY-MM-DD)}
        {--days=3 : Trailing window ending today when no --date (default 3)}';

    protected $description = 'Build Artemis-source daily performance (Pancake POS + Meta Ads) per user.';

    public function handle(): int
    {
        $dates = $this->resolveDates();
        $total = 0;

        Workspace::where('is_gencys_partner', false)
            ->with(['users' => fn ($query) => $query->has('pages')])
            ->get()
            ->each(function (Workspace $workspace) use ($dates, &$total) {
                $this->line($workspace->name);
                $this->line($workspace->users->count());
                foreach ($dates as $date) {
                    foreach ($workspace->users as $user) {
                        $this->buildForWorkspaceDate($workspace, $user->id, $date);
                        $total++;
                    }
                }
            });

        $this->info("Built {$total} Artemis performance row(s) across ".count($dates).' day(s).');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function resolveDates(): array
    {
        if ($this->option('date')) {
            return [Carbon::parse($this->option('date'))->toDateString()];
        }

        $days = max(1, (int) $this->option('days'));
        $end = Carbon::today();

        return collect(range($days - 1, 0))
            ->map(fn (int $i) => $end->copy()->subDays($i)->toDateString())
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function buildForWorkspaceDate(Workspace $workspace, int $userId, string $date): int
    {
        $sales = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $orders = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->count('*');

        $ad_spent = Insight::whereHas('adSet', function ($query) use ($userId) {
            $query->whereHas('page', fn ($query) => $query->where('owner_id', $userId));
        })->where('date', $date)
            ->sum('spend');

        $roas = $ad_spent ? $sales / $ad_spent : 0;

        $delivered = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('delivered_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->count('*');

        $delivered_amount = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('delivered_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $returned = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('returned_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->count('*');

        $returned_amount = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('returned_at', $date)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $returning = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('returning_at', '<=', $date)
            ->whereNull('returned_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->count('*');

        $returning_amount = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('returning_at', '<=', $date)
            ->whereNull('returned_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $overall_returning = $returning_amount + $returned_amount;

        $rts_rate = $overall_returning ? $overall_returning / ($overall_returning + $delivered_amount) * 100 : 0;

        $startOfMonth = Carbon::parse($date)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::parse($date)->endOfMonth()->format('Y-m-d');

        $date_to_month_sales = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', '>=', $startOfMonth)
            ->whereDate('confirmed_at', '<=', $endOfMonth)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_orders = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', '>=', $startOfMonth)
            ->whereDate('confirmed_at', '<=', $endOfMonth)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->count('*');

        $date_to_month_ad_spent = Insight::whereHas('adSet', function ($query) use ($userId) {
            $query->whereHas('page', fn ($query) => $query->where('owner_id', $userId));
        })->where('date', '>=', $startOfMonth)
            ->where('date', '<=', $endOfMonth)
            ->sum('spend');

        $date_to_month_roas = $date_to_month_ad_spent ? $date_to_month_sales / $date_to_month_ad_spent : 0;

        $date_to_month_sales_order_delivered_amount = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', '>=', $startOfMonth)
            ->whereDate('confirmed_at', '<=', $endOfMonth)
            ->whereNotNull('delivered_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_sales_order_returned_amount = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', '>=', $startOfMonth)
            ->whereDate('confirmed_at', '<=', $endOfMonth)
            ->whereNotNull('returned_at')
            ->whereNotNull('returning_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_sales_order_returning_amount = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', '>=', $startOfMonth)
            ->whereDate('confirmed_at', '<=', $endOfMonth)
            ->whereNotNull('returning_at')
            ->whereNull('returned_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_sales_order_overall_returning = $date_to_month_sales_order_returning_amount + $date_to_month_sales_order_returned_amount;

        $date_to_month_sales_order_rts_rate = $date_to_month_sales_order_overall_returning ? $date_to_month_sales_order_overall_returning / ($date_to_month_sales_order_overall_returning + $date_to_month_sales_order_delivered_amount) * 100 : 0;

        $date_to_month_shipped_out_delivered = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', '>=', $startOfMonth)
            ->whereDate('confirmed_at', '<=', $endOfMonth)
            ->whereNotNull('delivered_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_shipped_out_returned = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', '>=', $startOfMonth)
            ->whereDate('confirmed_at', '<=', $endOfMonth)
            ->whereNotNull('returned_at')
            ->whereNotNull('returning_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_shipped_out_returning = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('confirmed_at', '>=', $startOfMonth)
            ->whereDate('confirmed_at', '<=', $endOfMonth)
            ->whereNotNull('returning_at')
            ->whereNull('returned_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_shipped_out_overall_returning = $date_to_month_shipped_out_returning + $date_to_month_shipped_out_returned;

        $date_to_month_shipped_out_rts_rate = $date_to_month_shipped_out_overall_returning ? $date_to_month_shipped_out_overall_returning / ($date_to_month_shipped_out_overall_returning + $date_to_month_shipped_out_delivered) * 100 : 0;

        $date_to_month_parcel_status_delivered = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('delivered_at', '>=', $startOfMonth)
            ->whereDate('delivered_at', '<=', $endOfMonth)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_parcel_status_returned = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('returned_at', '>=', $startOfMonth)
            ->whereDate('returned_at', '<=', $endOfMonth)
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_parcel_status_returning = Order::whereHas('page', fn ($query) => $query->where('owner_id', $userId))
            ->whereDate('returning_at', '<=', $endOfMonth)
            ->whereNull('returned_at')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->sum('total_amount');

        $date_to_month_parcel_status_overall_returning = $date_to_month_parcel_status_returning + $date_to_month_parcel_status_returned;

        $date_to_month_parcel_status_rts_rate = $date_to_month_parcel_status_overall_returning ? $date_to_month_parcel_status_overall_returning / ($date_to_month_parcel_status_overall_returning + $date_to_month_parcel_status_delivered) * 100 : 0;

        AdvertiserPerformanceDailyRecord::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'source' => AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS,
                'advertiser_model' => 'user',
                'advertiser_id' => $userId,
                'date' => $date,
            ],
            [
                'advertiser_name' => User::find($userId)?->name,
                'orders' => $orders,
                'sales' => $sales,
                'ad_spent' => $ad_spent,
                'roas' => $roas,
                'delivered' => $delivered,
                'delivered_amount' => $delivered_amount,
                'returned' => $returned,
                'returned_amount' => $returned_amount,
                'returning' => $returning,
                'rts_rate' => $rts_rate,
                'date_to_month_sales' => $date_to_month_sales,
                'date_to_month_orders' => $date_to_month_orders,
                'date_to_month_ad_spent' => $date_to_month_ad_spent,
                'date_to_month_roas' => $date_to_month_roas,
                // Amount-based (returning maps to the for_return column).
                'date_to_month_sales_order_delivered' => $date_to_month_sales_order_delivered_amount,
                'date_to_month_sales_order_returned' => $date_to_month_sales_order_returned_amount,
                'date_to_month_sales_order_for_return' => $date_to_month_sales_order_returning_amount,
                'date_to_month_sales_order_rts_rate' => $date_to_month_sales_order_rts_rate,
                'date_to_month_shipped_out_delivered' => $date_to_month_shipped_out_delivered,
                'date_to_month_shipped_out_returned' => $date_to_month_shipped_out_returned,
                'date_to_month_shipped_out_for_return' => $date_to_month_shipped_out_returning,
                'date_to_month_shipped_out_rts_rate' => $date_to_month_shipped_out_rts_rate,
                'date_to_month_parcel_status_delivered' => $date_to_month_parcel_status_delivered,
                'date_to_month_parcel_status_returned' => $date_to_month_parcel_status_returned,
                'date_to_month_parcel_status_for_return' => $date_to_month_parcel_status_returning,
                'date_to_month_parcel_status_rts_rate' => $date_to_month_parcel_status_rts_rate,
            ],
        );

        return 1;
    }
}
