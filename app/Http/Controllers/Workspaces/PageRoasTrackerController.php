<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SalesMarketingDashboard;
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
 * Day cells are stored rows read verbatim; only the summary rows and the All
 * Pages group combine anything, and all of that lives in PageRoasTally.
 *
 * Every metric is always sent; which of them are shown is a client-side column
 * toggle (Orders/Sales/Ad Spend/ROAS by default), so switching a column on is
 * instant rather than a round trip.
 */
class PageRoasTrackerController extends Controller
{
    use AuthorizesRequests;

    /** What a day cell renders, read verbatim by PageRoasTally::stored(). */
    private const FIELDS = [
        'page_type', 'page_id', 'date',
        'orders', 'sales', 'ad_spent', 'ad_sales', 'ad_purchases',
        'delivered_amount', 'returning_amount',
        'roas', 'ad_roas', 'ad_cpp', 'cpp', 'rts_rate',
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
        'SUM(sales) AS sales',
        'SUM(ad_spent) AS ad_spent',
        'SUM(ad_sales) AS ad_sales',
        'SUM(ad_purchases) AS ad_purchases',
        'SUM(delivered_amount) AS delivered_amount',
        'SUM(returning_amount) AS returning_amount',
        'SUM(sales) / NULLIF(SUM(ad_spent), 0) AS roas',
        'SUM(ad_sales) / NULLIF(SUM(ad_spent), 0) AS ad_roas',
        'SUM(ad_spent) / NULLIF(SUM(ad_purchases), 0) AS ad_cpp',
        'SUM(ad_spent) / NULLIF(SUM(orders), 0) AS cpp',
        'SUM(returning_amount) / NULLIF(SUM(returning_amount) + SUM(delivered_amount), 0) * 100 AS rts_rate',
    ];

    /** The amounts, which the Average row divides. Ratios never divide. */
    private const AMOUNTS = [
        'orders', 'sales', 'ad_spent', 'ad_sales', 'ad_purchases',
        'delivered_amount', 'returning_amount',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        // Rendered as the "Page ROAS Tracker" tab of the S&M dashboard, so it
        // shares that dashboard's gating (module flag + permission).
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $user = $request->user();
        [$start, $end] = $this->resolveRange($request);
        $dates = $this->datesInRange($start, $end);
        $filters = $this->filters($request);

        $base = $this->baseQuery($workspace, $user, $filters, $start, $end);

        [$pages, $overall] = $this->build($base, $dates);

        return Inertia::render('workspaces/page-roas-tracker/index', [
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
            'tabs' => SalesMarketingDashboard::tabs($workspace),
            'activeTab' => 'page-roas-tracker',
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
    private function build(Builder $base, array $dates): array
    {
        $records = (clone $base)->select(self::FIELDS)->get();

        $names = $this->resolvePageNames($records);
        $dayCount = max(count($dates), 1);
        $aggregates = $this->aggregates($dayCount);

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

        // A ratio with no denominator stays null — "no cost per purchase" is not
        // the same figure as "a cost of zero".
        $ratio = fn (string $field) => ($row->{$field} ?? null) === null
            ? null
            : round((float) $row->{$field}, 2);

        return [
            'orders' => (int) round((float) ($row->{$prefix.'orders'} ?? 0)),
            'sales' => $amount('sales'),
            'ad_spent' => $amount('ad_spent'),
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
    private function aggregates(int $dayCount): string
    {
        $average = array_map(
            fn (string $field) => "SUM({$field}) / {$dayCount} AS avg_{$field}",
            self::AMOUNTS,
        );

        return implode(', ', [...self::AGGREGATES, ...$average]);
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
