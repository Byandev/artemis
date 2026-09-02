<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SalesMarketingDashboard;
use App\Support\TeamVisibility;
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
 * dates down the side, one metric column group per page, with per-page Total
 * and Average rows.
 *
 * Every metric is always sent; which of them are shown is a client-side column
 * toggle (Orders/Sales/Ad Spend/ROAS by default), so switching a column on is
 * instant rather than a round trip.
 */
class PageRoasTrackerController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace): Response
    {
        // Rendered as the "Page ROAS Tracker" tab of the S&M dashboard, so it
        // shares that dashboard's gating (module flag + permission).
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        [$start, $end] = $this->resolveRange($request);
        $dates = $this->datesInRange($start, $end);

        $user = $request->user();
        $selectedPages = $this->ids($request->input('pages'));
        $selectedShops = $this->ids($request->input('shops'));
        $selectedUsers = $this->ids($request->input('users'));

        // Team visibility: scoped users only see their team(s)' pages, and the
        // "viewing as team" switcher narrows everyone to the chosen team.
        $scoped = TeamVisibility::shouldScope($user, $workspace);
        $hasFilters = $selectedPages || $selectedShops || $selectedUsers;

        // Page / Shop / User facets combine (AND) with team visibility into a set
        // of eligible Pancake page ids; null means "no page scoping — every page"
        // (only possible for unrestricted users with no facet filter applied).
        $eligiblePageIds = ($scoped || $hasFilters)
            ? Page::query()
                ->where('workspace_id', $workspace->id)
                ->when($scoped, fn ($q) => $q->visibleTo($user, $workspace))
                ->when($selectedPages, fn ($q) => $q->whereIn('id', $selectedPages))
                ->when($selectedShops, fn ($q) => $q->whereIn('shop_id', $selectedShops))
                ->when($selectedUsers, fn ($q) => $q->whereIn('owner_id', $selectedUsers))
                ->pluck('id')
                ->all()
            : null;

        $records = DB::table('page_daily_records')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$start, $end])
            ->when($eligiblePageIds !== null, fn ($q) => $q
                ->where('page_type', Page::class)
                ->whereIn('page_id', $eligiblePageIds))
            ->select(
                'page_type', 'page_id', 'date',
                // Shown as-is on a day row.
                'orders', 'sales', 'ad_spent', 'ad_sales', 'delivered_amount',
                'returning_amount', 'roas', 'ad_roas', 'rts_rate', 'ad_cpp', 'cpp',
                // Only the Total/Average rows need these, to re-blend the ratios
                // over the range.
                'ad_purchases', 'returned_amount',
            )
            ->get();

        $names = $this->resolvePageNames($records);
        $dayCount = max(count($dates), 1);

        $pages = $records
            ->groupBy(fn ($r) => $r->page_type.'|'.$r->page_id)
            ->map(function (Collection $group, string $key) use ($dates, $names, $dayCount) {
                $byDate = $group->keyBy('date');

                $totals = self::EMPTY_TALLY;
                $days = [];

                foreach ($dates as $date) {
                    $rec = $byDate->get($date);

                    // A day cell is the row the builder wrote, verbatim.
                    $days[$date] = $this->day($rec);

                    $tally = $this->tally($rec);

                    foreach ($tally as $field => $value) {
                        if ($field !== 'returning') {
                            $totals[$field] += $value;
                        }
                    }

                    // `returning` is a stock, not a flow — each row already reads
                    // "still on the way back as of that day", so the range figure
                    // is the closing snapshot rather than the sum of the days. A
                    // day the builder skipped carries the last one forward.
                    if ($byDate->has($date)) {
                        $totals['returning'] = $tally['returning'];
                    }
                }

                [, $pageId] = explode('|', $key, 2);

                return [
                    'page_id' => $pageId,
                    'name' => $names[$key] ?? ('Page '.$pageId),
                    'days' => $days,
                    'total' => $this->summary($totals),
                    // Average = per-day mean of the amounts; every ratio stays
                    // blended over the whole range, so it matches the Total row.
                    'average' => $this->summary($totals, $dayCount),
                ];
            })
            // Most active pages first, so the useful columns are left-most.
            ->sortByDesc(fn ($page) => $page['total']['sales'])
            ->values();

        return Inertia::render('workspaces/page-roas-tracker/index', [
            'workspace' => $workspace,
            'dates' => $dates,
            'pages' => $pages,
            'filterOptions' => $this->filterOptions($workspace, $user),
            'query' => [
                'start' => $start,
                'end' => $end,
                // String ids so they round-trip into the filter's value model.
                'pages' => array_map('strval', $selectedPages),
                'shops' => array_map('strval', $selectedShops),
                'users' => array_map('strval', $selectedUsers),
            ],
            'tabs' => SalesMarketingDashboard::tabs($workspace),
            'activeTab' => 'page-roas-tracker',
        ]);
    }

    /**
     * Normalise a request value to a de-duped list of positive ints.
     *
     * @return list<int>
     */
    private function ids(mixed $value): array
    {
        return collect((array) $value)
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();
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
     * One day, exactly as `build-page-daily-performance` stored it.
     *
     * Nothing is derived here. The builder owns every formula, and the tracker
     * having its own copy is what silently drifted once already — a change to
     * the builder's cost-per-purchase had no effect on the page, because the
     * page was quietly recomputing the old one.
     *
     * @return array<string, float|int|null>
     */
    private function day(?object $rec): array
    {
        // A ratio the builder had no denominator for stays null — "no cost per
        // purchase" is not the same figure as "a cost of zero".
        $ratio = fn (string $field) => $rec?->{$field} === null ? null : (float) $rec->{$field};

        return [
            'orders' => (int) ($rec->orders ?? 0),
            'sales' => (float) ($rec->sales ?? 0),
            'ad_spent' => (float) ($rec->ad_spent ?? 0),
            'ad_sales' => (float) ($rec->ad_sales ?? 0),
            'delivered_amount' => (float) ($rec->delivered_amount ?? 0),
            'returning_amount' => (float) ($rec->returning_amount ?? 0),
            'roas' => $ratio('roas'),
            'ad_roas' => $ratio('ad_roas'),
            'rts_rate' => $ratio('rts_rate'),
            'ad_cpp' => $ratio('ad_cpp'),
            'cpp' => $ratio('cpp'),
        ];
    }

    /**
     * The ingredients the Total/Average rows are built from, all zeroed.
     *
     * Only the summary rows need these: no stored row covers a date range, and
     * a ratio cannot be summed, so a range has to go back to the counts and
     * amounts underneath it.
     */
    private const EMPTY_TALLY = [
        'orders' => 0.0,
        'sales' => 0.0,
        'ad_spent' => 0.0,
        'ad_sales' => 0.0,
        'ad_purchases' => 0.0,
        'returning' => 0.0,
        'returned' => 0.0,
        'delivered' => 0.0,
    ];

    /**
     * One day's ingredients, or all zeroes for a day the builder never wrote.
     *
     * @return array<string, float>
     */
    private function tally(?object $rec): array
    {
        return [
            'orders' => (float) ($rec->orders ?? 0),
            'sales' => (float) ($rec->sales ?? 0),
            'ad_spent' => (float) ($rec->ad_spent ?? 0),
            'ad_sales' => (float) ($rec->ad_sales ?? 0),
            'ad_purchases' => (float) ($rec->ad_purchases ?? 0),
            'returning' => (float) ($rec->returning_amount ?? 0),
            'returned' => (float) ($rec->returned_amount ?? 0),
            'delivered' => (float) ($rec->delivered_amount ?? 0),
        ];
    }

    /**
     * The Total ($divisor 1) or Average ($divisor = day count) row.
     *
     * Amounts divide; ratios never do — they are blended from the undivided
     * tally, so the range reports its true ROAS/CPP/RTS rather than a mean of
     * daily ratios, which would let a ₱10 day weigh as much as a ₱10,000 one.
     * These formulas mirror the builder's; they are the one place the tracker
     * still derives anything, because a range has no stored row to read.
     *
     * @param  array<string, float>  $t
     * @return array<string, float|int|null>
     */
    private function summary(array $t, int $divisor = 1): array
    {
        $per = fn (float $v) => $divisor > 1 ? $v / $divisor : $v;
        $ratio = fn (float $num, float $den) => $den > 0 ? round($num / $den, 2) : null;

        // RTS counts both legs of a return — in-flight and completed — against
        // what actually landed, matching the builder.
        $returning = $t['returning'] + $t['returned'];
        $rtsBase = $returning + $t['delivered'];

        return [
            'orders' => (int) round($per($t['orders'])),
            'sales' => round($per($t['sales']), 2),
            'ad_spent' => round($per($t['ad_spent']), 2),
            'ad_sales' => round($per($t['ad_sales']), 2),
            'delivered_amount' => round($per($t['delivered']), 2),
            // A stock, so it passes through undivided on every row.
            'returning_amount' => round($t['returning'], 2),
            'roas' => $ratio($t['sales'], $t['ad_spent']),
            'ad_roas' => $ratio($t['ad_sales'], $t['ad_spent']),
            'rts_rate' => $rtsBase > 0 ? round($returning / $rtsBase * 100, 2) : null,
            'ad_cpp' => $ratio($t['ad_spent'], $t['ad_purchases']),
            'cpp' => $ratio($t['ad_spent'], $t['orders']),
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
