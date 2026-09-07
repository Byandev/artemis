<?php

namespace App\Http\Controllers\Workspaces\SalesMarketing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page ROAS Tracker: reads the page_daily_records built by
 * `build-page-daily-performance` and lays them out as a day-by-day breakdown —
 * dates down the side, one metric column group per page, then an All Pages
 * group, each with Total and Average rows.
 *
 * Day cells are stored rows read verbatim — bar the budget comparison, which is
 * arithmetic on two of the row's own columns; only the summary rows and the All
 * Pages group combine anything, and all of that lives in PageRoasTally.
 *
 * Every metric is always sent; which of them are shown is a client-side column
 * toggle (Orders/Sales/Ad Spend/Budget/Var/ROAS by default), so switching a
 * column on is instant rather than a round trip.
 *
 * On top of the recorded figures it carries an estimated margin — see
 * estimateSelects(). It is an envelope calculation, not the income statement:
 * flat courier rates and a flat freight cost, against each page's recent RTS.
 */
class PageRoasTrackerController extends Controller
{
    use AuthorizesRequests;

    /**
     * The assumptions behind the estimated margin.
     *
     * They mirror the pancake side of the income statement rather than reading
     * from it: this is a back-of-envelope figure on a tracker, and it should not
     * move because the finance module changed its mind mid-month.
     */
    private const COD_FEE_RATE = 0.0275;

    private const VAT_RATE = 0.12;

    /** Freight per parcel. A flat figure — the tracker has no per-order fee. */
    private const SHIPPING_FEE = 67;

    /** The RTS a page is assumed to run at when last month can't say. */
    private const DEFAULT_RTS_RATE = 18.0;

    /** What a day cell renders, read verbatim by PageRoasTally::stored(). */
    private const FIELDS = [
        'page_type', 'page_id', 'date',
        'orders', 'item_quantity', 'order_cogs',
        'sales', 'ad_spent', 'ad_spend_budget', 'ad_sales', 'ad_purchases',
        'delivered_amount', 'returning_amount',
        'roas', 'ad_roas', 'ad_cpp', 'cpp', 'rts_rate',
    ];

    /**
     * The budget comparison, per day — the one thing the day grain derives.
     *
     * It is arithmetic on two columns of the same row rather than a figure of
     * its own, so it is worked out here instead of stored: a spend and a budget
     * that disagree with their own variance is a drift the table cannot have.
     *
     * A null budget stays null all the way through (NULL - x and x / NULL are
     * both NULL), so a page nobody budgeted reads as "no variance", not as a
     * page that overspent its entire spend.
     */
    private const DAY_DERIVED = [
        'ad_spent - ad_spend_budget AS budget_variance',
        'ad_spent / NULLIF(ad_spend_budget, 0) * 100 AS budget_pace',
    ];

    /**
     * Every summary cell, worked out by the database.
     *
     * Aliased to the stored column names so an aggregate row and a stored row
     * are read by the same code — see cell().
     *
     * The ratios are computed across the whole range rather than averaged from
     * the daily ones: a mean of daily ROAS would let a ₱10 day weigh as much as
     * a ₱10,000 one. NULLIF leaves a zero denominator null instead of dividing
     * by it, so "no cost per purchase" never renders as "a cost of zero".
     *
     * RTS blends the same way: what went back over what moved, across the range.
     * A mean of the stored daily rates would let a page-day with two deliveries
     * outvote one with two hundred, and would count every day with no delivery
     * activity at all as a 0% day.
     */
    private const AGGREGATES = [
        'SUM(orders) AS orders',
        'SUM(item_quantity) AS item_quantity',
        'SUM(order_cogs) AS order_cogs',
        'SUM(sales) AS sales',
        'SUM(ad_spent) AS ad_spent',
        'SUM(ad_spend_budget) AS ad_spend_budget',
        'SUM(ad_sales) AS ad_sales',
        'SUM(ad_purchases) AS ad_purchases',
        'SUM(delivered_amount) AS delivered_amount',
        'SUM(returning_amount) AS returning_amount',
        'SUM(sales) / NULLIF(SUM(ad_spent), 0) AS roas',
        'SUM(ad_sales) / NULLIF(SUM(ad_spent), 0) AS ad_roas',
        'SUM(ad_spent) / NULLIF(SUM(ad_purchases), 0) AS ad_cpp',
        'SUM(ad_spent) / NULLIF(SUM(orders), 0) AS cpp',
        'SUM(returning_amount) / NULLIF(SUM(returning_amount) + SUM(delivered_amount), 0) * 100 AS rts_rate',
        // Spent against budgeted, over the whole range — the pace is a ratio
        // like the rest, so it blends rather than averaging the daily ones.
        'SUM(ad_spent) / NULLIF(SUM(ad_spend_budget), 0) * 100 AS budget_pace',
    ];

    /** The amounts, which the Average row divides. Ratios never divide. */
    private const AMOUNTS = [
        'orders', 'item_quantity', 'order_cogs',
        'sales', 'ad_spent', 'ad_spend_budget', 'ad_sales', 'ad_purchases',
        'delivered_amount', 'returning_amount',
    ];

    /**
     * Amounts that are a difference between sums rather than a column of their
     * own, keyed by the SQL that produces them.
     *
     * Pesos, not a ratio, so the Average row divides them like any other amount
     * — a month ₱30,000 over budget is ₱1,000 over per day.
     */
    private const DERIVED_AMOUNTS = [
        'budget_variance' => 'SUM(ad_spent) - SUM(ad_spend_budget)',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        // Rendered as the "Page ROAS Tracker" tab of the S&M dashboard, so it
        // shares that dashboard's gating (module flag + permission).
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        // Gencys partners don't get this page at all — it is hidden from their
        // sidebar, so a bookmarked URL shouldn't walk in behind that.
        abort_if($workspace->is_gencys_partner, 404);

        $this->authorize(Permission::ViewPageRoasTracker->value, $workspace);

        $user = $request->user();
        [$start, $end] = $this->resolveRange($request);
        $dates = $this->datesInRange($start, $end);
        $filters = $this->filters($request);

        $base = $this->baseQuery($workspace, $user, $filters, $start, $end);

        // The margin estimate discounts revenue by the RTS each page has been
        // running at, measured over the month before the range in view.
        $rtsRates = $this->assumedRtsRates($workspace, $start);

        [$pages, $overall] = $this->build($base, $dates, $rtsRates);

        return Inertia::render('workspaces/sales-marketing/page-roas-tracker/index', [
            'workspace' => $workspace,
            'dates' => $dates,
            'pages' => $pages,
            'overall' => $overall,
            'filterOptions' => $this->filterOptions($workspace, $user),
            'query' => [
                'start' => $start,
                'end' => $end,
                // String ids so they round-trip into the filter's value model.
                'pages' => array_map('strval', $filters['pages']),
                'shops' => array_map('strval', $filters['shops']),
                'users' => array_map('strval', $filters['users']),
            ],
        ]);
    }

    /**
     * Shape the stored rows into one series per page, plus the All Pages group.
     *
     * Day cells come from the rows themselves; every summary is a SQL aggregate
     * — one per grain — so nothing is added up in PHP.
     *
     * @param  list<string>  $dates
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
     */
    private function build(Builder $base, array $dates, array $rtsRates): array
    {
        $factor = $this->rtsFactor($rtsRates);

        $records = (clone $base)
            ->selectRaw(implode(', ', [
                ...self::FIELDS,
                ...self::DAY_DERIVED,
                ...$this->estimateSelects($factor, fn (string $e, string $name) => "{$e} AS {$name}"),
            ]))
            ->get();

        $names = $this->resolvePageNames($records);
        $dayCount = max(count($dates), 1);
        $aggregates = $this->aggregates($dayCount, $factor);

        $perPage = (clone $base)
            ->selectRaw("page_type, page_id, {$aggregates}")
            ->groupBy('page_type', 'page_id')
            ->get()
            ->keyBy(fn ($r) => $r->page_type.'|'.$r->page_id);

        $perDate = (clone $base)
            ->selectRaw("date, {$aggregates}")
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $grand = (clone $base)->selectRaw($aggregates)->first();

        $pages = [];

        foreach ($records->groupBy(fn ($r) => $r->page_type.'|'.$r->page_id) as $key => $group) {
            $byDate = $group->keyBy('date');

            $days = [];
            foreach ($dates as $date) {
                $days[$date] = $this->cell($byDate->get($date));
            }

            $totals = $perPage->get($key);

            [, $pageId] = explode('|', $key, 2);

            $pages[] = [
                'page_id' => $pageId,
                'name' => $names[$key] ?? ('Page '.$pageId),
                // What the margin estimate assumed for this page, so the figure
                // can be read rather than taken on faith.
                'assumed_rts' => round($rtsRates[(int) $pageId] ?? self::DEFAULT_RTS_RATE, 2),
                'days' => $days,
                'total' => $this->cell($totals),
                'average' => $this->cell($totals, 'avg_'),
            ];
        }

        if ($pages === []) {
            return [[], null];
        }

        // Most active pages first, so the useful columns are left-most.
        usort($pages, fn ($a, $b) => $b['total']['sales'] <=> $a['total']['sales']);

        return [$pages, [
            'days' => collect($dates)
                ->mapWithKeys(fn (string $d) => [$d => $this->cell($perDate->get($d))])
                ->all(),
            'total' => $this->cell($grand),
            'average' => $this->cell($grand, 'avg_'),
        ]];
    }

    /**
     * One cell of the grid, from either a stored row or a SQL aggregate.
     *
     * Both arrive under the same field names — AGGREGATES aliases its sums and
     * ratios to the stored column names — so the day cells, the Total rows and
     * the All Pages group all read the same way. Nothing is derived here: the
     * builder owns the daily figures and the database owns the range ones.
     *
     * $prefix picks the Average row's pre-divided amounts, which the same query
     * selects as avg_*. Ratios are never prefixed — they cover the whole range
     * either way, so the Average row reads the Total row's.
     *
     * @return array<string, float|int|null>
     */
    private function cell(?object $row, string $prefix = ''): array
    {
        $amount = fn (string $field) => round((float) ($row->{$prefix.$field} ?? 0), 2);

        // An amount that means nothing when it was never recorded. A page with
        // no budget on file is not a page budgeted at zero, so it stays blank
        // rather than reading as "₱0 planned, every peso an overspend".
        $optional = fn (string $field) => ($row->{$prefix.$field} ?? null) === null
            ? null
            : round((float) $row->{$prefix.$field}, 2);

        // A ratio with no denominator stays null — "no cost per purchase" is not
        // the same figure as "a cost of zero".
        $ratio = fn (string $field) => ($row->{$field} ?? null) === null
            ? null
            : round((float) $row->{$field}, 2);

        return [
            'orders' => (int) round((float) ($row->{$prefix.'orders'} ?? 0)),
            // Units, like orders — a count, not an amount.
            'item_quantity' => (int) round((float) ($row->{$prefix.'item_quantity'} ?? 0)),
            // What the day's goods cost. Null when none of its lines were costed.
            'order_cogs' => $optional('order_cogs'),
            'sales' => $amount('sales'),
            'ad_spent' => $amount('ad_spent'),
            'ad_spend_budget' => $optional('ad_spend_budget'),
            // Spend minus budget: positive is over, negative is under.
            'budget_variance' => $optional('budget_variance'),
            'ad_sales' => $amount('ad_sales'),
            // A count, like orders — Meta's purchases, the denominator of ad_cpp.
            'ad_purchases' => (int) round((float) ($row->{$prefix.'ad_purchases'} ?? 0)),
            'delivered_amount' => $amount('delivered_amount'),
            'returning_amount' => $amount('returning_amount'),
            'roas' => $ratio('roas'),
            'ad_roas' => $ratio('ad_roas'),
            'ad_cpp' => $ratio('ad_cpp'),
            'cpp' => $ratio('cpp'),
            'rts_rate' => $ratio('rts_rate'),
            // Spend as a percentage of budget: 100 is on plan.
            'budget_pace' => $ratio('budget_pace'),

            // The estimated margin and its parts. All amounts, so the Average
            // row divides them like any other.
            'est_delivered_amount' => $amount('est_delivered_amount'),
            'est_cod_fee' => $amount('est_cod_fee'),
            'est_cod_fee_vat' => $amount('est_cod_fee_vat'),
            'est_cogs' => $amount('est_cogs'),
            'est_shipping_fee' => $amount('est_shipping_fee'),
            'est_gross_profit' => $amount('est_gross_profit'),
        ];
    }

    /**
     * The summary select: Total figures, plus the Average row's amounts already
     * divided by the calendar day count.
     *
     * One query serves both rows. The day count is the calendar span, so days
     * with no rows still count — an average over a week is over seven days
     * whether or not the builder wrote all seven.
     */
    private function aggregates(int $dayCount, string $factor): string
    {
        $total = self::AGGREGATES;

        $average = array_map(
            fn (string $field) => "SUM({$field}) / {$dayCount} AS avg_{$field}",
            self::AMOUNTS,
        );

        // A derived amount has no column to SUM, so both rows are spelled out
        // from the one expression rather than repeating it.
        foreach (self::DERIVED_AMOUNTS as $field => $expression) {
            $total[] = "{$expression} AS {$field}";
            $average[] = "({$expression}) / {$dayCount} AS avg_{$field}";
        }

        // Every estimate is linear in the stored columns, so summing the
        // expression over the range gives the same answer as adding up the days
        // it produced — which is what lets one definition serve all three rows.
        $total = [...$total, ...$this->estimateSelects($factor, fn ($e, $n) => "SUM({$e}) AS {$n}")];
        $average = [...$average, ...$this->estimateSelects($factor, fn ($e, $n) => "SUM({$e}) / {$dayCount} AS avg_{$n}")];

        return implode(', ', [...$total, ...$average]);
    }

    /**
     * The estimated margin and the costs it takes off, as SQL over the stored
     * columns — one definition, wrapped by $shape into whichever grain is being
     * selected.
     *
     * The chain is the pancake income statement's, worked page-day by page-day:
     * revenue is discounted by the page's RTS (what actually gets collected),
     * the courier's COD fee and its VAT come off that, cost of goods is
     * discounted the same way (returned stock comes back), and freight is
     * charged on every parcel — a parcel that comes back was still shipped.
     *
     * @param  callable(string, string): string  $shape  (expression, name) => select
     * @return list<string>
     */
    private function estimateSelects(string $factor, callable $shape): array
    {
        $delivered = "COALESCE(sales, 0) * {$factor}";
        $cod = "({$delivered}) * ".self::COD_FEE_RATE;
        $vat = "({$cod}) * ".self::VAT_RATE;
        $cogs = "COALESCE(order_cogs, 0) * {$factor}";
        $shipping = 'COALESCE(orders, 0) * '.self::SHIPPING_FEE;

        $estimates = [
            'est_delivered_amount' => $delivered,
            'est_cod_fee' => $cod,
            'est_cod_fee_vat' => $vat,
            'est_cogs' => $cogs,
            'est_shipping_fee' => $shipping,
            'est_gross_profit' => "({$delivered}) - COALESCE(ad_spent, 0) - ({$cod}) - ({$vat}) - ({$cogs}) - ({$shipping})",
        ];

        return array_values(array_map(
            fn (string $name) => $shape($estimates[$name], $name),
            array_keys($estimates),
        ));
    }

    /**
     * The share of revenue a page is assumed to actually collect, as SQL.
     *
     * A CASE over page_id rather than one blended rate, because the All Pages
     * roll-up has to be the sum of each page discounted by its own — there is no
     * single rate that would give the same answer. Pages last month says nothing
     * about fall through to the default.
     *
     * @param  array<int, float>  $rates  page id => RTS percentage
     */
    private function rtsFactor(array $rates): string
    {
        $default = $this->factorFor(self::DEFAULT_RTS_RATE);
        $cases = '';

        foreach ($rates as $pageId => $rate) {
            $cases .= ' WHEN '.(int) $pageId.' THEN '.$this->factorFor($rate);
        }

        return $cases === '' ? "({$default})" : "(CASE page_id{$cases} ELSE {$default} END)";
    }

    /**
     * One page's RTS percentage as the fraction of revenue left over.
     *
     * Clamped to 0–1: a rate outside 0–100% is a broken measurement, and letting
     * it through would hand the estimate negative revenue.
     */
    private function factorFor(float $rate): string
    {
        return (string) round(max(0.0, min(1.0, 1 - ($rate / 100))), 6);
    }

    /**
     * Each page's RTS over the calendar month before the range in view, as a
     * percentage — the rate the margin estimate discounts its revenue by.
     *
     * Blended over the month (what went back over what moved) rather than
     * averaged from the daily rates, for the same reason the Total row blends:
     * a day with two parcels shouldn't weigh as much as one with two hundred.
     *
     * A page that moved nothing last month is absent, and falls back to the
     * default rather than being called a 0% page.
     *
     * @return array<int, float>
     */
    private function assumedRtsRates(Workspace $workspace, string $start): array
    {
        $month = Carbon::parse($start)->subMonthNoOverflow();

        return DB::table('page_daily_records')
            ->where('workspace_id', $workspace->id)
            ->where('page_type', Page::class)
            ->whereBetween('date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->groupBy('page_id')
            ->selectRaw('page_id, SUM(returning_amount) / NULLIF(SUM(returning_amount) + SUM(delivered_amount), 0) * 100 AS rts_rate')
            ->get()
            ->filter(fn ($row) => $row->rts_rate !== null)
            ->mapWithKeys(fn ($row) => [(int) $row->page_id => (float) $row->rts_rate])
            ->all();
    }

    /**
     * The rows for the range, narrowed to the pages this viewer may see.
     *
     * Returned unexecuted: every grain — day rows, per-page totals, per-date
     * totals, the grand total — is a clone of this one query.
     *
     * @param  array{pages: list<int>, shops: list<int>, users: list<int>}  $filters
     */
    private function baseQuery(Workspace $workspace, User $user, array $filters, string $start, string $end): Builder
    {
        $pageIds = $this->eligiblePageIds($workspace, $user, $filters);

        return DB::table('page_daily_records')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$start, $end])
            ->when($pageIds !== null, fn ($q) => $q
                ->where('page_type', Page::class)
                ->whereIn('page_id', $pageIds));
    }

    /**
     * The Page / Shop / User facets combined (AND) with team visibility.
     *
     * @param  array{pages: list<int>, shops: list<int>, users: list<int>}  $filters
     * @return list<int>|null null means "no page scoping — every page"
     */
    private function eligiblePageIds(Workspace $workspace, User $user, array $filters): ?array
    {
        // Team visibility: scoped users only see their team(s)' pages, and the
        // "viewing as team" switcher narrows everyone to the chosen team.
        $scoped = TeamVisibility::shouldScope($user, $workspace);

        // Only an unrestricted viewer with no facet applied skips scoping.
        if (! $scoped && ! array_filter($filters)) {
            return null;
        }

        return Page::query()
            ->where('workspace_id', $workspace->id)
            ->when($scoped, fn ($q) => $q->visibleTo($user, $workspace))
            ->when($filters['pages'], fn ($q) => $q->whereIn('id', $filters['pages']))
            ->when($filters['shops'], fn ($q) => $q->whereIn('shop_id', $filters['shops']))
            ->when($filters['users'], fn ($q) => $q->whereIn('owner_id', $filters['users']))
            ->pluck('id')
            ->all();
    }

    /**
     * The facets from the request, each a de-duped list of positive ints.
     *
     * @return array{pages: list<int>, shops: list<int>, users: list<int>}
     */
    private function filters(Request $request): array
    {
        $ids = fn (mixed $value) => collect((array) $value)
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'pages' => $ids($request->input('pages')),
            'shops' => $ids($request->input('shops')),
            'users' => $ids($request->input('users')),
        ];
    }

    /**
     * Options for the combined Page / Shop / User filter, as {key, label} pairs
     * (key = the id as a string, matching the filter's value model).
     *
     * @return array{pages: mixed, shops: mixed, users: mixed}
     */
    private function filterOptions(Workspace $workspace, User $user): array
    {
        // Only offer pages/shops the viewer can actually see under team scoping.
        $scoped = TeamVisibility::shouldScope($user, $workspace);

        return [
            'pages' => $workspace->pages()
                ->when($scoped, fn ($q) => $q->visibleTo($user, $workspace))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($p) => ['key' => (string) $p->id, 'label' => $p->name ?: ('Page '.$p->id)]),
            'shops' => $workspace->shops()
                ->when($scoped, fn ($q) => $q->visibleTo($user, $workspace))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['key' => (string) $s->id, 'label' => $s->name ?: ('Shop '.$s->id)]),
            'users' => $workspace->users()
                ->orderBy('users.name')
                ->get(['users.id', 'users.name'])
                ->map(fn ($u) => ['key' => (string) $u->id, 'label' => $u->name]),
        ];
    }

    /**
     * Every date in the inclusive range as Y-m-d.
     *
     * @return list<string>
     */
    private function datesInRange(string $start, string $end): array
    {
        $dates = [];

        for ($d = Carbon::parse($start); $d->lte($end); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $dates;
    }

    /**
     * Resolve the range from the request, defaulting to the trailing 7 days.
     *
     * @return array{0: string, 1: string} [start, end] as Y-m-d
     */
    private function resolveRange(Request $request): array
    {
        $end = $this->parseDate($request->input('end')) ?? Carbon::today();
        $start = $this->parseDate($request->input('start')) ?? $end->copy()->subDays(6);

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Display names for the polymorphic pages, batched per morph type so both
     * Artemis (App\Models\Page) and Gencys pages resolve in one query each.
     *
     * @param  Collection<int, object>  $records
     * @return array<string, string|null> keyed by "{page_type}|{page_id}"
     */
    private function resolvePageNames(Collection $records): array
    {
        $names = [];

        foreach ($records->groupBy('page_type') as $type => $group) {
            if (! is_string($type) || ! class_exists($type)) {
                continue;
            }

            $ids = $group->pluck('page_id')->unique()->all();
            $found = $type::query()->whereKey($ids)->pluck('name', 'id');

            foreach ($ids as $id) {
                $names[$type.'|'.$id] = $found[$id] ?? null;
            }
        }

        return $names;
    }
}
