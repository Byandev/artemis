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
 * dates down the side, one Orders/Sales/Ad Spend/ROAS column group per page,
 * with per-page Total and Average rows.
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
            ->select('page_type', 'page_id', 'date', 'orders', 'sales', 'ad_spent')
            ->get();

        $names = $this->resolvePageNames($records);
        $dayCount = max(count($dates), 1);

        $pages = $records
            ->groupBy(fn ($r) => $r->page_type.'|'.$r->page_id)
            ->map(function (Collection $group, string $key) use ($dates, $names, $dayCount) {
                $byDate = $group->keyBy('date');

                $sumOrders = 0;
                $sumSales = 0.0;
                $sumAdSpent = 0.0;
                $days = [];

                foreach ($dates as $date) {
                    $rec = $byDate->get($date);
                    $orders = (int) ($rec->orders ?? 0);
                    $sales = (float) ($rec->sales ?? 0);
                    $adSpent = (float) ($rec->ad_spent ?? 0);

                    $days[$date] = $this->metrics($orders, $sales, $adSpent);

                    $sumOrders += $orders;
                    $sumSales += $sales;
                    $sumAdSpent += $adSpent;
                }

                [, $pageId] = explode('|', $key, 2);

                return [
                    'page_id' => $pageId,
                    'name' => $names[$key] ?? ('Page '.$pageId),
                    'days' => $days,
                    'total' => $this->metrics($sumOrders, $sumSales, $sumAdSpent),
                    // Average = per-day mean over the range; ROAS stays blended.
                    'average' => $this->metrics(
                        (int) round($sumOrders / $dayCount),
                        $sumSales / $dayCount,
                        $sumAdSpent / $dayCount,
                        blendedRoasFrom: [$sumSales, $sumAdSpent],
                    ),
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
     * One metrics cell. ROAS is sales/ad_spent unless an explicit blended pair is
     * given (used by the Average row so it reports the period ROAS, not a mean).
     *
     * @param  array{0: float, 1: float}|null  $blendedRoasFrom  [sales, adSpent]
     * @return array{orders: int, sales: float, ad_spent: float, roas: float|null}
     */
    private function metrics(int $orders, float $sales, float $adSpent, ?array $blendedRoasFrom = null): array
    {
        [$roasSales, $roasAdSpent] = $blendedRoasFrom ?? [$sales, $adSpent];

        return [
            'orders' => $orders,
            'sales' => round($sales, 2),
            'ad_spent' => round($adSpent, 2),
            'roas' => $roasAdSpent > 0 ? round($roasSales / $roasAdSpent, 2) : null,
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
