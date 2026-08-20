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
use Modules\MetaAds\Models\Campaign;

class AdsCalendarController extends Controller
{
    /**
     * Ads Calendar — a month grid showing how many campaigns were created on each
     * day, broken down either per Facebook page or per page owner. Campaigns carry
     * no page of their own, so the page is derived from their ad sets'
     * meta_page_id; a Pancake page's primary key IS that FB page id, so we join
     * straight to `pages` for the name. The owner hangs off `pages.owner_id`.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $month = $this->resolveMonth($request);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $view = $this->resolveView($request);

        // Only the workspace's actively-synced accounts the member is allowed to
        // see (mirrors the Ads Manager scoping).
        $accountIds = AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->visibleTo($request->user(), $workspace)
            ->pluck('meta_ads_accounts.id');

        // The filter is keyed per dimension so an existing `?pages[]=…` link keeps
        // working untouched. ('none' = campaigns with no resolvable page.)
        $selected = array_values(array_filter(
            (array) $request->query($view === 'user' ? 'users' : 'pages', []),
            fn ($p) => is_string($p) && $p !== '' && $p !== 'all',
        ));

        $groupOptions = $view === 'user'
            ? $this->userOptions($workspace, $request->user(), $accountIds->all(), $start, $end)
            : $this->pageOptions($workspace, $request->user(), $accountIds->all(), $start, $end);

        $rows = $accountIds->isEmpty()
            ? collect()
            : $this->dailyCampaignCounts($accountIds->all(), $start, $end, $view, $selected);

        $unassignedLabel = $view === 'user' ? 'Unassigned owner' : 'Unassigned page';

        return Inertia::render('workspaces/integrations/meta-ads/calendar', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'month' => $start->format('Y-m'),
            'monthLabel' => $start->format('F Y'),
            'prevMonth' => $start->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $start->copy()->addMonthNoOverflow()->format('Y-m'),
            // Disallow paging into the future — there can't be ad sets there yet.
            'canGoNext' => $start->lessThan(Carbon::now()->startOfMonth()),
            'view' => $view,
            'days' => $this->buildDays($rows, $unassignedLabel),
            'groupTotals' => $this->buildGroupTotals($rows, $unassignedLabel),
            'groupOptions' => $groupOptions,
            'selected' => $selected,
        ]);
    }

    /**
     * One row per (day, group) with the number of distinct campaigns created,
     * where a group is either the page or the page's owner. The day is the
     * campaign's created date; the page is derived from the campaign's ad sets
     * (LEFT JOIN so a campaign with no ad set / no known page still surfaces as
     * unassigned). A campaign whose ad sets span several groups is counted once
     * per group (COUNT DISTINCT dedupes within a group bucket).
     */
    private function dailyCampaignCounts(array $accountIds, Carbon $start, Carbon $end, string $view, array $selected = [])
    {
        $byUser = $view === 'user';

        // Fixed identifiers chosen by $view, never user input — safe to inline.
        $idColumn = $byUser ? 'users.id' : 'meta_ads_sets.meta_page_id';
        $nameColumn = $byUser ? 'users.name' : 'pages.name';

        $query = Campaign::query()
            ->leftJoin('meta_ads_sets', 'meta_ads_sets.meta_ads_campaign_id', '=', 'meta_ads_campaigns.id')
            ->leftJoin('pages', 'pages.id', '=', 'meta_ads_sets.meta_page_id');

        if ($byUser) {
            $query->leftJoin('users', 'users.id', '=', 'pages.owner_id');
        }

        return $query
            ->whereIn('meta_ads_campaigns.meta_ads_account_id', $accountIds)
            ->whereNotNull('meta_ads_campaigns.start_time')
            ->whereBetween('meta_ads_campaigns.start_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when(! empty($selected), function ($q) use ($selected, $idColumn) {
                $ids = array_values(array_filter($selected, fn ($p) => $p !== 'none'));
                $includeUnassigned = in_array('none', $selected, true);

                $q->where(function ($w) use ($ids, $includeUnassigned, $idColumn) {
                    if (! empty($ids)) {
                        $w->whereIn($idColumn, $ids);
                    }
                    if ($includeUnassigned) {
                        $w->orWhereNull($idColumn);
                    }
                });
            })
            ->groupBy(DB::raw('DATE(meta_ads_campaigns.start_time)'), $idColumn, $nameColumn)
            ->orderBy(DB::raw('DATE(meta_ads_campaigns.start_time)'))
            ->get([
                DB::raw('DATE(meta_ads_campaigns.start_time) AS day'),
                DB::raw($idColumn.' AS group_id'),
                DB::raw($nameColumn.' AS group_name'),
                DB::raw('COUNT(DISTINCT meta_ads_campaigns.id) AS total'),
            ]);
    }

    /**
     * Filter options: every page in the workspace the user is allowed to see —
     * the canonical, month-stable list (pages with no campaigns are still listed).
     * An "Unassigned" bucket (keyed `none`) is appended only when the visible
     * month actually contains campaigns with no resolvable page.
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

        return $this->withUnassignedOption($options, $accountIds, $start, $end, 'Unassigned page');
    }

    /**
     * Filter options for the per-owner view: the owners of the pages this member
     * can see, so the option list respects the same page visibility scoping.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function userOptions(Workspace $workspace, User $user, array $accountIds, Carbon $start, Carbon $end): array
    {
        $options = User::query()
            ->whereIn('id', Page::query()
                ->where('workspace_id', $workspace->id)
                ->visibleTo($user, $workspace)
                ->select('pages.owner_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $owner) => [
                'id' => (string) $owner->id,
                'name' => $owner->name ?: 'Unnamed user',
            ])
            ->all();

        return $this->withUnassignedOption($options, $accountIds, $start, $end, 'Unassigned owner');
    }

    /**
     * Appends the unassigned bucket to a filter list, but only when the visible
     * month actually contains campaigns with no resolvable page.
     *
     * @param  array<int, array{id: string, name: string}>  $options
     * @return array<int, array{id: string, name: string}>
     */
    private function withUnassignedOption(array $options, array $accountIds, Carbon $start, Carbon $end, string $label): array
    {
        if (! empty($accountIds) && $this->hasUnassignedCampaigns($accountIds, $start, $end)) {
            $options[] = ['id' => 'none', 'name' => $label];
        }

        return $options;
    }

    /**
     * Whether the month has any campaign with no resolvable page — i.e. it has no
     * ad set, or an ad set with a NULL meta_page_id — within the visible accounts.
     * Drives whether the "Unassigned" filter option is offered.
     */
    private function hasUnassignedCampaigns(array $accountIds, Carbon $start, Carbon $end): bool
    {
        return Campaign::query()
            ->leftJoin('meta_ads_sets', 'meta_ads_sets.meta_ads_campaign_id', '=', 'meta_ads_campaigns.id')
            ->whereIn('meta_ads_campaigns.meta_ads_account_id', $accountIds)
            ->whereNotNull('meta_ads_campaigns.start_time')
            ->whereBetween('meta_ads_campaigns.start_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereNull('meta_ads_sets.meta_page_id')
            ->exists();
    }

    /**
     * Calendar payload keyed by `Y-m-d`, each day holding its grand total and a
     * per-group breakdown sorted by volume. Days with no campaigns are omitted (the
     * frontend renders the full grid and treats missing keys as empty).
     *
     * @return array<string, array{total: int, groups: array<int, array{id: string, name: string, count: int}>}>
     */
    private function buildDays($rows, string $unassignedLabel): array
    {
        $days = [];

        foreach ($rows as $row) {
            $day = (string) $row->day;

            if (! isset($days[$day])) {
                $days[$day] = ['total' => 0, 'groups' => []];
            }

            $count = (int) $row->total;
            $days[$day]['total'] += $count;
            $days[$day]['groups'][] = [
                'id' => $row->group_id === null ? 'none' : (string) $row->group_id,
                'name' => $row->group_name ?: $unassignedLabel,
                'count' => $count,
            ];
        }

        foreach ($days as &$day) {
            usort($day['groups'], fn ($a, $b) => $b['count'] <=> $a['count']);
        }

        return $days;
    }

    /**
     * Per-group totals across the whole month (powers the legend / summary list).
     *
     * @return array<int, array{id: string, name: string, count: int}>
     */
    private function buildGroupTotals($rows, string $unassignedLabel): array
    {
        return $rows
            ->groupBy(fn ($row) => $row->group_id === null ? 'none' : (string) $row->group_id)
            ->map(fn ($group) => [
                'id' => $group->first()->group_id === null ? 'none' : (string) $group->first()->group_id,
                'name' => $group->first()->group_name ?: $unassignedLabel,
                'count' => (int) $group->sum('total'),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * The dimension to break the month down by, from `?view=page|user`.
     */
    private function resolveView(Request $request): string
    {
        return $request->query('view') === 'user' ? 'user' : 'page';
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
