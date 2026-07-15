<?php

namespace App\Queries;

use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Support\AdvertiserVisibility;
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

    /**
     * Advertiser ids the viewer may see under team scoping, or null for "no
     * restriction". Applied to every record read via baseQuery().
     *
     * @var array<int, int>|null
     */
    private ?array $visibleAdvertiserIds;

    public function __construct(
        private readonly Workspace $workspace,
        array $internIds = [],
        private readonly ?string $date = null,
        ?User $viewer = null,
    ) {
        $this->internIds = array_values(array_filter(
            array_map('intval', $internIds),
            fn (int $id): bool => $id > 0,
        ));

        $this->source = $workspace->is_gencys_partner
            ? AdvertiserPerformanceDailyRecord::SOURCE_GENCYS
            : AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS;

        // Team visibility: scoped users only see advertisers on the team(s) they
        // can see; the "viewing as team" switcher narrows everyone to one team.
        $this->visibleAdvertiserIds = AdvertiserVisibility::visibleIds($viewer, $workspace, $this->source);
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
                    'kpis' => $this->emptyKpis(),
                    'by_advertiser' => [],
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
     * Chart datasets for the SELECTED DATE:
     *  - kpis: workspace totals with an up/down delta vs the previous day
     *  - by_advertiser: each shown advertiser's sales & ad spend for the day —
     *    drives the sales bar, ad spend bar, and ad spend share donut
     */
    private function charts(Carbon $date, Collection $interns): array
    {
        $internIds = $interns->pluck('id')->all();

        if (empty($internIds)) {
            return ['kpis' => $this->emptyKpis(), 'by_advertiser' => []];
        }

        // The selected day's record per advertiser (at most one each).
        $todayById = $this->recordsFor($date->toDateString())->keyBy('gencys_intern_id');

        $byAdvertiser = $interns
            ->map(function ($intern) use ($todayById) {
                $rec = $todayById->get($intern->id);

                return [
                    'name' => $intern->full_name,
                    'sales' => round((float) ($rec->sales ?? 0), 2),
                    'ad_spent' => round((float) ($rec->ad_spent ?? 0), 2),
                ];
            })
            // One shared order (highest sales first) so each advertiser keeps
            // the same colour/position across all three charts.
            ->sortByDesc('sales')
            ->values()
            ->all();

        return [
            'kpis' => $this->dayKpis($date, $internIds),
            'by_advertiser' => $byAdvertiser,
        ];
    }

    /**
     * Top KPI tiles for the selected date — workspace totals summed across the
     * shown advertisers: sales, ad spend, blended ROAS, and orders. Each tile
     * also carries a `deltas` entry comparing the selected day to the previous
     * day (over the same advertiser set), for the up/down arrows.
     *
     * @param  array<int, int>  $internIds  the advertisers shown for the date
     */
    private function dayKpis(Carbon $date, array $internIds): array
    {
        if (empty($internIds)) {
            return $this->emptyKpis();
        }

        $today = $this->dayTotals($date, $internIds);
        $prev = $this->dayTotals($date->copy()->subDay(), $internIds);

        return [
            'total_sales' => $today['sales'],
            'total_ad_spent' => $today['ad_spent'],
            'roas' => $today['roas'],
            'total_orders' => $today['orders'],
            'deltas' => [
                'total_sales' => $this->delta($today['sales'], $prev['sales']),
                'total_ad_spent' => $this->delta($today['ad_spent'], $prev['ad_spent']),
                'roas' => $this->delta($today['roas'], $prev['roas']),
                'total_orders' => $this->delta($today['orders'], $prev['orders']),
            ],
        ];
    }

    /**
     * Summed sales / ad spend / orders (and blended ROAS) for one date across
     * the given advertisers.
     *
     * @param  array<int, int>  $internIds
     * @return array{sales: float, ad_spent: float, orders: int, roas: float|null}
     */
    private function dayTotals(Carbon $date, array $internIds): array
    {
        $agg = $this->baseQuery()
            ->where('date', $date->toDateString())
            ->whereIn('advertiser_id', $internIds)
            ->selectRaw('
                SUM(COALESCE(sales, 0)) as sales,
                SUM(COALESCE(ad_spent, 0)) as ad_spent,
                SUM(COALESCE(orders, 0)) as orders
            ')
            ->first();

        $sales = (float) ($agg->sales ?? 0);
        $spend = (float) ($agg->ad_spent ?? 0);

        return [
            'sales' => round($sales, 2),
            'ad_spent' => round($spend, 2),
            'orders' => (int) ($agg->orders ?? 0),
            'roas' => $spend > 0 ? round($sales / $spend, 2) : null,
        ];
    }

    /**
     * Direction + percentage change of $cur vs $prev (nulls treated as 0). The
     * percentage is null when there's no previous value to compare against.
     *
     * @return array{pct: float|null, status: 'up'|'down'|'flat'}
     */
    private function delta(int|float|null $cur, int|float|null $prev): array
    {
        $c = (float) ($cur ?? 0);
        $p = (float) ($prev ?? 0);

        return [
            'pct' => $p != 0.0 ? round(($c - $p) / abs($p) * 100, 1) : null,
            'status' => $c > $p ? 'up' : ($c < $p ? 'down' : 'flat'),
        ];
    }

    /** Zeroed KPI payload (no data / no advertisers), with flat deltas. */
    private function emptyKpis(): array
    {
        $flat = ['pct' => null, 'status' => 'flat'];

        return [
            'total_sales' => 0.0,
            'total_ad_spent' => 0.0,
            'roas' => null,
            'total_orders' => 0,
            'deltas' => [
                'total_sales' => $flat,
                'total_ad_spent' => $flat,
                'roas' => $flat,
                'total_orders' => $flat,
            ],
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
            ->where('source', $this->source)
            // Team visibility: an empty set (scoped user with no visible
            // advertisers) yields whereIn(..., []) → no rows, failing closed.
            ->when(
                $this->visibleAdvertiserIds !== null,
                fn ($q) => $q->whereIn('advertiser_id', $this->visibleAdvertiserIds),
            );
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
     * The report date. An explicit date always wins. Otherwise default to
     * yesterday (today's data is still coming in) — but if yesterday has no
     * rows yet, fall back to the most recent earlier date that does, so the
     * day-scoped KPIs and table don't open empty while older days hold data.
     */
    private function resolveDate(): ?Carbon
    {
        if ($this->date) {
            return Carbon::parse($this->date);
        }

        $yesterday = Carbon::yesterday();

        if ($this->baseQuery()->where('date', $yesterday->toDateString())->exists()) {
            return $yesterday;
        }

        $latest = $this->baseQuery()
            ->where('date', '<=', $yesterday->toDateString())
            ->max('date');

        return $latest ? Carbon::parse($latest) : $yesterday;
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
