<?php

namespace Modules\GencysERP\Queries;

use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\GencysERP\Models\Intern;

/**
 * Builds the advertiser performance "Quick Data View (Sales/ROAS)" table for a
 * single date, reading the unified advertiser_performance_daily_records table.
 *
 * The source is chosen by the workspace: Gencys partners read the Gencys source
 * (advertiser = Intern); everyone else reads the Artemis source
 * (advertiser = User).
 *
 * Per advertiser the day's orders / sales / ad_spent are shown, with ROAS
 * (sales ÷ ad_spent) and a change vs the previous day's sales, ranked by sales.
 */
class AdvertiserDashboardQuery
{
    /** @var array<int, int> */
    private array $internIds;

    /** Which unified source this workspace reads ('gencys' | 'artemis'). */
    private string $source;

    public function __construct(
        private readonly Workspace $workspace,
        array $internIds = [],
        private readonly ?string $date = null,
    ) {
        $this->internIds = array_values(array_filter(
            array_map('intval', $internIds),
            fn (int $id): bool => $id > 0,
        ));

        $this->source = $workspace->is_gencys_partner
            ? AdvertiserPerformanceDailyRecord::SOURCE_GENCYS
            : AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS;
    }

    public function get(): array
    {
        $date = $this->resolveDate();

        if (! $date) {
            return [
                'date' => null,
                'date_label' => null,
                'prev_date' => null,
                'prev_date_label' => null,
                'rows' => [],
                'subtotal' => null,
                'ad_rts' => ['rows' => [], 'subtotal' => null],
                'charts' => [
                    'kpis' => [
                        'total_sales' => 0.0, 'total_ad_spent' => 0.0, 'roas' => null,
                        'total_orders' => 0, 'avg_rts_rate' => null,
                    ],
                    'sales_trend' => ['dates' => [], 'series' => []],
                    'ad_spend_trend' => ['dates' => [], 'series' => []],
                    'ad_spend_share' => [],
                ],
            ];
        }

        $prevDate = $date->copy()->subDay();

        // At most one record per intern per date (unique key), so index by intern.
        // "today" = the selected date, "yesterday" = the day before.
        $today = $this->recordsFor($date->toDateString())->keyBy('gencys_intern_id');
        $yesterday = $this->recordsFor($prevDate->toDateString())->keyBy('gencys_intern_id');

        // Month-to-date sales per intern: summed daily sales from the 1st of the
        // selected date's month through that date. (The ERP's own
        // date_to_month_sales snapshot isn't populated, so we derive it.)
        $monthToDate = $this->monthToDateSales($date);

        $interns = $this->advertisers($today->keys()->all());

        $rows = $interns->map(function ($intern) use ($today, $yesterday, $monthToDate) {
            $t = $today->get($intern->id);
            $y = $yesterday->get($intern->id);

            $tSales = (float) ($t->sales ?? 0);
            $ySales = (float) ($y->sales ?? 0);
            $tSpend = (float) ($t->ad_spent ?? 0);
            $ySpend = (float) ($y->ad_spent ?? 0);
            $change = $tSales - $ySales;

            return [
                'id' => $intern->id,
                'name' => $intern->full_name,
                'orders' => (int) ($t->orders ?? 0),
                'sales' => $tSales,
                'yesterday_sales' => $ySales,
                'change' => round($change, 2),
                // Up/down remark: today's sales vs yesterday's.
                'status' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
                // Month-to-date sales as of the selected date (running total).
                'month_sales' => (float) ($monthToDate[$intern->id] ?? 0),
                'roas_yesterday' => $ySpend > 0 ? round($ySales / $ySpend, 2) : null,
                'roas_today' => $tSpend > 0 ? round($tSales / $tSpend, 2) : null,
                // Internal — kept for blended subtotal ROAS, stripped below.
                '_t_spend' => $tSpend,
                '_y_spend' => $ySpend,
            ];
        });

        // Rank by month-to-date sales (1 = highest) and return rows in that order.
        $ranked = $rows->sortByDesc('month_sales')->values();

        return [
            'date' => $date->toDateString(),
            'date_label' => $date->format('M j'),
            'prev_date' => $prevDate->toDateString(),
            'prev_date_label' => $prevDate->format('M j'),
            'rows' => $ranked
                ->map(function (array $row, int $i) {
                    $row['rank'] = $i + 1;
                    unset($row['_t_spend'], $row['_y_spend']);

                    return $row;
                })
                ->values()
                ->all(),
            'subtotal' => $this->subtotal($rows),
            // Second table: ad spend + month-to-date RTS per intern.
            'ad_rts' => $this->adRtsRows($date, $interns),
            // Chart data (month-to-date daily series + per-intern aggregates).
            'charts' => $this->charts($date, $interns),
        ];
    }

    /**
     * Chart datasets, all month-to-date through the selected date:
     *  - kpis: workspace totals (sales, ad spend, blended ROAS, orders, avg RTS)
     *  - sales_trend: daily sales per intern (one series each)
     *  - ad_spend_trend: daily ad spend per intern (one series each)
     *  - ad_spend_share: per-intern MTD ad spend total (for the pie)
     */
    private function charts(Carbon $date, Collection $interns): array
    {
        $empty = [
            'kpis' => [
                'total_sales' => 0.0, 'total_ad_spent' => 0.0, 'roas' => null,
                'total_orders' => 0, 'avg_rts_rate' => null,
            ],
            'sales_trend' => ['dates' => [], 'series' => []],
            'ad_spend_trend' => ['dates' => [], 'series' => []],
            'ad_spend_share' => [],
        ];

        $internIds = $interns->pluck('id')->all();
        if (empty($internIds)) {
            return $empty;
        }

        $nameById = $interns->pluck('full_name', 'id');
        $monthStart = $date->copy()->startOfMonth();

        $records = $this->baseQuery()
            ->whereIn('advertiser_id', $internIds)
            ->whereBetween('date', [$monthStart->toDateString(), $date->toDateString()])
            ->get(['advertiser_id as gencys_intern_id', 'date', 'sales', 'ad_spent', 'orders']);

        // The month-to-date day axis, and an [intern][date] lookup.
        $dates = [];
        for ($d = $monthStart->copy(); $d->lte($date); $d->addDay()) {
            $dates[] = $d->toDateString();
        }
        $byIntern = [];
        foreach ($records as $r) {
            $byIntern[$r->gencys_intern_id][$r->date->toDateString()] = $r;
        }

        // Per-intern daily sales & ad spend series (same shape, one series each)
        // + per-intern MTD ad spend total for the pie.
        $salesSeries = [];
        $adSpendSeries = [];
        $adSpendShare = [];
        $totSales = 0.0;
        $totSpend = 0.0;
        $totOrders = 0;
        foreach ($internIds as $id) {
            $salesByDay = [];
            $adByDay = [];
            $s = 0.0;
            $sp = 0.0;
            $o = 0;
            foreach ($dates as $dt) {
                $rec = $byIntern[$id][$dt] ?? null;
                $salesByDay[] = (float) ($rec->sales ?? 0);
                $adByDay[] = (float) ($rec->ad_spent ?? 0);
                $s += (float) ($rec->sales ?? 0);
                $sp += (float) ($rec->ad_spent ?? 0);
                $o += (int) ($rec->orders ?? 0);
            }
            $name = $nameById[$id] ?? "#{$id}";
            $salesSeries[] = ['name' => $name, 'data' => $salesByDay];
            $adSpendSeries[] = ['name' => $name, 'data' => $adByDay];
            $adSpendShare[] = ['name' => $name, 'ad_spent' => round($sp, 2)];
            $totSales += $s;
            $totSpend += $sp;
            $totOrders += $o;
        }

        // Blended RTS rate from the month-to-date snapshot (latest record per
        // intern in the month), summed across interns:
        //   (Σfor_return + Σreturned) / (Σfor_return + Σreturned + Σdelivered)
        $rts = $this->monthToDateRts($date);
        $rtsDelivered = 0.0;
        $rtsReturned = 0.0;
        $rtsForReturn = 0.0;
        foreach ($rts as $r) {
            $rtsDelivered += (float) ($r->date_to_month_sales_order_delivered ?? 0);
            $rtsReturned += (float) ($r->date_to_month_sales_order_returned ?? 0);
            $rtsForReturn += (float) ($r->date_to_month_sales_order_for_return ?? 0);
        }
        $rtsDenom = $rtsForReturn + $rtsReturned + $rtsDelivered;
        $rtsRate = $rtsDenom > 0
            ? round(($rtsForReturn + $rtsReturned) / $rtsDenom * 100, 2)
            : null;

        return [
            'kpis' => [
                'total_sales' => round($totSales, 2),
                'total_ad_spent' => round($totSpend, 2),
                'roas' => $totSpend > 0 ? round($totSales / $totSpend, 2) : null,
                'total_orders' => $totOrders,
                'avg_rts_rate' => $rtsRate,
            ],
            'sales_trend' => ['dates' => $dates, 'series' => $salesSeries],
            'ad_spend_trend' => ['dates' => $dates, 'series' => $adSpendSeries],
            'ad_spend_share' => $adSpendShare,
        ];
    }

    /**
     * Second table's rows: actual ad spend, 3-day average ad spend, and the
     * month-to-date RTS rate + amount per intern.
     *
     * @return array{rows: array, subtotal: array}
     */
    private function adRtsRows(Carbon $date, Collection $interns): array
    {
        // Selected-day ad spend per intern.
        $spendToday = $this->recordsFor($date->toDateString())->keyBy('gencys_intern_id');

        // 3-day ad spend sum: selected day + the two days before it.
        $spend3d = $this->baseQuery()
            ->whereBetween('date', [$date->copy()->subDays(2)->toDateString(), $date->toDateString()])
            ->when($this->internIds, fn ($q) => $q->whereIn('advertiser_id', $this->internIds))
            ->groupBy('advertiser_id')
            ->selectRaw('advertiser_id, SUM(COALESCE(ad_spent, 0)) as spend')
            ->pluck('spend', 'advertiser_id');

        // Month-to-date RTS snapshot: taken from the latest record in the
        // selected date's month (the ERP stamps date_to_month_* only on its most
        // recent day), so it reflects month-to-date as of the selected date.
        $rts = $this->monthToDateRts($date);

        // Target ad spend per intern: the selected day's page daily budgets for
        // the pages owned by the intern's linked user.
        $target = $this->targetAdSpend($date, $interns->pluck('id')->all());

        $rows = $interns
            ->map(function ($intern) use ($spendToday, $spend3d, $rts, $target) {
                $rec = $spendToday->get($intern->id);
                $r = $rts->get($intern->id);

                $rate = $r && $r->date_to_month_sales_order_rts_rate !== null
                    ? (float) $r->date_to_month_sales_order_rts_rate
                    : null;
                $returned = (float) ($r->date_to_month_sales_order_returned ?? 0);
                $forReturn = (float) ($r->date_to_month_sales_order_for_return ?? 0);
                $hasRts = $rate !== null || $returned != 0.0 || $forReturn != 0.0;
                $tgt = $target->get($intern->id);

                return [
                    'id' => $intern->id,
                    'name' => $intern->full_name,
                    'actual_ad_spent' => (float) ($rec->ad_spent ?? 0),
                    'target_ad_spent' => $tgt !== null ? (float) $tgt : null,
                    'avg_ad_spent' => round((float) ($spend3d[$intern->id] ?? 0) / 3, 2),
                    'rts_rate' => $rate,
                    'rts_amount' => $hasRts ? $returned + $forReturn : null,
                ];
            })
            ->sortByDesc('actual_ad_spent')
            ->values();

        return [
            'rows' => $rows->all(),
            'subtotal' => [
                'actual_ad_spent' => (float) $rows->sum('actual_ad_spent'),
                'target_ad_spent' => (float) $rows->sum(fn ($r) => $r['target_ad_spent'] ?? 0),
                'avg_ad_spent' => (float) $rows->sum('avg_ad_spent'),
                // RTS rate is a percentage — not summable, so no subtotal.
                'rts_rate' => null,
                'rts_amount' => (float) $rows->sum(fn ($r) => $r['rts_amount'] ?? 0),
            ],
        ];
    }

    /**
     * advertiser id => target ad spend for $date: the sum of page daily budgets
     * for the pages owned by the advertiser's user. Each page uses its latest
     * budget recorded ON OR BEFORE $date (carries forward on un-recorded days).
     *
     * Source-aware linkage to the owning user:
     *   - gencys:  intern → gencys_interns.user_id → pages.owner_id
     *   - artemis: advertiser_id IS the user id     → pages.owner_id
     *
     * @param  array<int, int>  $advertiserIds
     */
    private function targetAdSpend(Carbon $date, array $advertiserIds): Collection
    {
        if (empty($advertiserIds)) {
            return collect();
        }

        // Per page: the date of its most recent budget on or before $date.
        $latestPerPage = DB::table('page_daily_budget_records')
            ->where('workspace_id', $this->workspace->id)
            ->where('date', '<=', $date->toDateString())
            ->groupBy('page_id')
            ->selectRaw('page_id, MAX(date) as latest_date');

        $joinLatestBudget = function ($join) {
            $join->on('b.page_id', '=', 'lp.page_id')
                ->on('b.date', '=', 'lp.latest_date');
        };

        // Gencys: advertiser is an Intern → its user_id → pages.owner_id.
        if ($this->source === AdvertiserPerformanceDailyRecord::SOURCE_GENCYS) {
            return DB::table('gencys_interns as gi')
                ->join('pages as p', 'p.owner_id', '=', 'gi.user_id')
                ->joinSub($latestPerPage, 'lp', 'lp.page_id', '=', 'p.id')
                ->join('page_daily_budget_records as b', $joinLatestBudget)
                ->where('gi.workspace_id', $this->workspace->id)
                ->whereIn('gi.id', $advertiserIds)
                ->whereNotNull('gi.user_id')
                ->groupBy('gi.id')
                ->selectRaw('gi.id as id, SUM(b.budget) as target')
                ->pluck('target', 'id');
        }

        // Artemis: advertiser_id is the user id → pages.owner_id directly.
        return DB::table('pages as p')
            ->joinSub($latestPerPage, 'lp', 'lp.page_id', '=', 'p.id')
            ->join('page_daily_budget_records as b', $joinLatestBudget)
            ->where('p.workspace_id', $this->workspace->id)
            ->whereIn('p.owner_id', $advertiserIds)
            ->groupBy('p.owner_id')
            ->selectRaw('p.owner_id as id, SUM(b.budget) as target')
            ->pluck('target', 'id');
    }

    /**
     * intern id => the latest record within [start of $date's month, $date],
     * carrying its date_to_month_sales_order_* RTS snapshot.
     */
    private function monthToDateRts(Carbon $date): Collection
    {
        return $this->baseQuery()
            ->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->toDateString()])
            ->when($this->internIds, fn ($q) => $q->whereIn('advertiser_id', $this->internIds))
            ->orderBy('date')
            ->get([
                'advertiser_id as gencys_intern_id',
                'date',
                'date_to_month_sales_order_rts_rate',
                'date_to_month_sales_order_delivered',
                'date_to_month_sales_order_returned',
                'date_to_month_sales_order_for_return',
            ])
            ->groupBy('gencys_intern_id')
            ->map(fn ($group) => $group->last());
    }

    /**
     * Base query over the unified advertiser_performance_daily_records table,
     * scoped to this workspace's rows for the resolved source.
     */
    private function baseQuery()
    {
        return AdvertiserPerformanceDailyRecord::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('source', $this->source);
    }

    /**
     * The advertiser dimension for the given ids, source-aware:
     *   - gencys: active Interns
     *   - artemis: Users
     * Returned rows expose ->id and ->full_name for source-agnostic reads.
     *
     * @param  array<int, int>  $ids
     */
    private function advertisers(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        if ($this->source === AdvertiserPerformanceDailyRecord::SOURCE_GENCYS) {
            return Intern::where('workspace_id', $this->workspace->id)
                ->where('active', true)
                ->whereIn('id', $ids)
                ->orderBy('intern_id')
                ->get(['id', 'full_name']);
        }

        // Artemis: only users who belong to this workspace (member or owner).
        return User::whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereHas('workspaces', fn ($w) => $w->where('workspaces.id', $this->workspace->id))
                    ->orWhere('id', $this->workspace->owner_id);
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => (object) ['id' => $u->id, 'full_name' => $u->name]);
    }

    /** Daily records for the given date, scoped to workspace + selected interns. */
    private function recordsFor(string $date): Collection
    {
        return $this->baseQuery()
            ->where('date', $date)
            ->when($this->internIds, fn ($q) => $q->whereIn('advertiser_id', $this->internIds))
            ->get(['advertiser_id as gencys_intern_id', 'orders', 'sales', 'ad_spent']);
    }

    /**
     * intern id => summed daily sales from the 1st of $date's month through
     * $date (month-to-date), scoped to workspace + selected interns.
     */
    private function monthToDateSales(Carbon $date): Collection
    {
        return $this->baseQuery()
            ->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->toDateString()])
            ->when($this->internIds, fn ($q) => $q->whereIn('advertiser_id', $this->internIds))
            ->groupBy('advertiser_id')
            ->selectRaw('advertiser_id, SUM(COALESCE(sales, 0)) as mtd_sales')
            ->pluck('mtd_sales', 'advertiser_id');
    }

    /**
     * The report date. Defaults to yesterday (today's data is still coming in),
     * falling back to the most recent record on or before yesterday so there's
     * always data to show.
     */
    private function resolveDate(): ?Carbon
    {
        if ($this->date) {
            return Carbon::parse($this->date);
        }

        $latest = $this->baseQuery()
            ->where('date', '<=', Carbon::yesterday()->toDateString())
            ->when($this->internIds, fn ($q) => $q->whereIn('advertiser_id', $this->internIds))
            ->max('date');

        return $latest ? Carbon::parse($latest) : null;
    }

    private function subtotal(Collection $rows): array
    {
        $tSales = (float) $rows->sum('sales');
        $ySales = (float) $rows->sum('yesterday_sales');
        $tSpend = (float) $rows->sum('_t_spend');
        $ySpend = (float) $rows->sum('_y_spend');
        $change = $tSales - $ySales;

        return [
            'orders' => (int) $rows->sum('orders'),
            'sales' => $tSales,
            'yesterday_sales' => $ySales,
            'change' => round($change, 2),
            'status' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'month_sales' => (float) $rows->sum('month_sales'),
            'roas_yesterday' => $ySpend > 0 ? round($ySales / $ySpend, 2) : null,
            'roas_today' => $tSpend > 0 ? round($tSales / $tSpend, 2) : null,
        ];
    }
}
