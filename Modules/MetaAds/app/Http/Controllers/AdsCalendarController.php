<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;

class AdsCalendarController extends Controller
{
    /**
     * Ads Calendar — a month grid showing how many ad sets were created on each
     * day, broken down per Facebook page. Ad sets carry the FB page id in
     * meta_page_id, and a Pancake page's primary key IS that FB page id, so we
     * join straight to `pages` for a human-readable page name.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $month = $this->resolveMonth($request);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Only the workspace's actively-synced accounts the member is allowed to
        // see (mirrors the Ads Manager scoping).
        $accountIds = AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->visibleTo($request->user(), $workspace)
            ->pluck('meta_ads_accounts.id');

        // Optional multi-page filter ('none' = ad sets with no resolvable page).
        $selectedPages = array_values(array_filter(
            (array) $request->query('pages', []),
            fn ($p) => is_string($p) && $p !== '' && $p !== 'all',
        ));

        $pageOptions = $this->pageOptions($workspace, $request->user(), $accountIds->all(), $start, $end);

        $rows = $accountIds->isEmpty()
            ? collect()
            : $this->dailyCountsPerPage($accountIds->all(), $start, $end, $selectedPages);

        return Inertia::render('workspaces/integrations/meta-ads/calendar', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'month' => $start->format('Y-m'),
            'monthLabel' => $start->format('F Y'),
            'prevMonth' => $start->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $start->copy()->addMonthNoOverflow()->format('Y-m'),
            // Disallow paging into the future — there can't be ad sets there yet.
            'canGoNext' => $start->lessThan(Carbon::now()->startOfMonth()),
            'days' => $this->buildDays($rows),
            'pageTotals' => $this->buildPageTotals($rows),
            'pageOptions' => $pageOptions,
            'selectedPages' => $selectedPages,
        ]);
    }

    /**
     * One row per (day, page) with the number of ad sets created. The page name
     * is resolved via the pages table (LEFT JOIN so ad sets without a known page
     * still surface under an "Unassigned page" bucket).
     */
    private function dailyCountsPerPage(array $accountIds, Carbon $start, Carbon $end, array $selectedPages = [])
    {
        return AdSet::query()
            ->leftJoin('pages', 'pages.id', '=', 'meta_ads_sets.meta_page_id')
            ->whereIn('meta_ads_sets.meta_ads_account_id', $accountIds)
            ->whereNotNull('meta_ads_sets.created_time')
            ->whereBetween('meta_ads_sets.created_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when(! empty($selectedPages), function ($q) use ($selectedPages) {
                $ids = array_values(array_filter($selectedPages, fn ($p) => $p !== 'none'));
                $includeUnassigned = in_array('none', $selectedPages, true);

                $q->where(function ($w) use ($ids, $includeUnassigned) {
                    if (! empty($ids)) {
                        $w->whereIn('meta_ads_sets.meta_page_id', $ids);
                    }
                    if ($includeUnassigned) {
                        $w->orWhereNull('meta_ads_sets.meta_page_id');
                    }
                });
            })
            ->groupBy(DB::raw('DATE(meta_ads_sets.created_time)'), 'meta_ads_sets.meta_page_id', 'pages.name')
            ->orderBy(DB::raw('DATE(meta_ads_sets.created_time)'))
            ->get([
                DB::raw('DATE(meta_ads_sets.created_time) AS day'),
                'meta_ads_sets.meta_page_id',
                'pages.name AS page_name',
                DB::raw('COUNT(*) AS total'),
            ]);
    }

    /**
     * Filter options: every page in the workspace the user is allowed to see —
     * the canonical, month-stable list (pages with no ad sets are still listed).
     * An "Unassigned" bucket (keyed `none`) is appended only when the visible
     * month actually contains ad sets that carry no page.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function pageOptions(Workspace $workspace, User $user, array $accountIds, Carbon $start, Carbon $end): array
    {
        $options = Page::query()
            ->where('workspace_id', $workspace->id)
            ->visibleTo($user, $workspace)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Page $page) => [
                'id' => (string) $page->id,
                'name' => $page->name ?: 'Untitled page',
            ])
            ->all();

        if (! empty($accountIds) && $this->hasUnassignedAdSets($accountIds, $start, $end)) {
            $options[] = ['id' => 'none', 'name' => 'Unassigned page'];
        }

        return $options;
    }

    /**
     * Whether the month has any ad sets with no page (meta_page_id NULL) within
     * the visible accounts — drives whether the "Unassigned" filter is offered.
     */
    private function hasUnassignedAdSets(array $accountIds, Carbon $start, Carbon $end): bool
    {
        return AdSet::query()
            ->whereIn('meta_ads_sets.meta_ads_account_id', $accountIds)
            ->whereNotNull('meta_ads_sets.created_time')
            ->whereBetween('meta_ads_sets.created_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereNull('meta_ads_sets.meta_page_id')
            ->exists();
    }

    /**
     * Calendar payload keyed by `Y-m-d`, each day holding its grand total and a
     * per-page breakdown sorted by volume. Days with no ad sets are omitted (the
     * frontend renders the full grid and treats missing keys as empty).
     *
     * @return array<string, array{total: int, pages: array<int, array{id: string, name: string, count: int}>}>
     */
    private function buildDays($rows): array
    {
        $days = [];

        foreach ($rows as $row) {
            $day = (string) $row->day;

            if (! isset($days[$day])) {
                $days[$day] = ['total' => 0, 'pages' => []];
            }

            $count = (int) $row->total;
            $days[$day]['total'] += $count;
            $days[$day]['pages'][] = [
                'id' => $row->meta_page_id === null ? 'none' : (string) $row->meta_page_id,
                'name' => $row->page_name ?: 'Unassigned page',
                'count' => $count,
            ];
        }

        foreach ($days as &$day) {
            usort($day['pages'], fn ($a, $b) => $b['count'] <=> $a['count']);
        }

        return $days;
    }

    /**
     * Per-page totals across the whole month (powers the legend / summary list).
     *
     * @return array<int, array{id: string, name: string, count: int}>
     */
    private function buildPageTotals($rows): array
    {
        return $rows
            ->groupBy(fn ($row) => $row->meta_page_id === null ? 'none' : (string) $row->meta_page_id)
            ->map(fn ($group) => [
                'id' => (string) $group->first()->meta_page_id ?: 'none',
                'name' => $group->first()->page_name ?: 'Unassigned page',
                'count' => (int) $group->sum('total'),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * The month to display, from `?month=Y-m`, defaulting to the current month.
     * Invalid input falls back to the current month rather than erroring.
     */
    private function resolveMonth(Request $request): Carbon
    {
        $value = (string) $request->query('month', '');

        try {
            return $value !== ''
                ? Carbon::createFromFormat('Y-m', $value)->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::now()->startOfMonth();
        }
    }
}
