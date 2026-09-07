<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\Campaign;

class AdsCalendarController extends Controller
{
    /**
     * Ads Calendar — a month grid showing how many campaigns STARTED on each day,
     * broken down either per Facebook page or per page owner.
     *
     * The day is `meta_ads_campaigns.start_time`, not `created_time`: a campaign
     * built today to begin next week belongs to next week. Both columns are
     * synced, so switching the calendar to creation date is a one-column change
     * — but it would be a different question, and the two disagree whenever a
     * campaign is scheduled ahead.
     *
     * Campaigns carry no page of their own, so the page is derived from their ad
     * sets' meta_page_id; a Pancake page's primary key IS that FB page id, so we
     * join straight to `pages` for the name. The owner hangs off `pages.owner_id`.
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

        // The day's headline is asked for separately rather than added up from
        // the breakdown — see dailyCampaignTotals().
        $dayTotals = $accountIds->isEmpty()
            ? collect()
            : $this->dailyCampaignTotals($accountIds->all(), $start, $end, $view, $selected);

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
            'days' => $this->buildDays($rows, $dayTotals, $unassignedLabel),
            'groupTotals' => $this->buildGroupTotals($rows, $unassignedLabel),
            'groupOptions' => $groupOptions,
            'selected' => $selected,
        ]);
    }

    /**
     * One row per (day, group) with the number of distinct campaigns that started,
     * where a group is either the page or the page's owner. The page is derived
     * from the campaign's ad sets (LEFT JOIN so a campaign with no ad set / no
     * known page still surfaces as unassigned).
     *
     * The count is campaigns, not ad sets: three ad sets of one campaign on one
     * page are one campaign, which is what COUNT DISTINCT collapses them to. A
     * campaign whose ad sets span several groups is counted once in each of
     * them, so these rows can add up to more than the day's own total.
     */
    private function dailyCampaignCounts(array $accountIds, Carbon $start, Carbon $end, string $view, array $selected = [])
    {
        [$idColumn, $nameColumn] = $this->groupColumns($view);

        return $this->baseQuery($accountIds, $start, $end, $view, $selected)
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
     * How many campaigns actually started on each day — the number the day cell
     * shows.
     *
     * It has to be asked of the database separately, because adding up the
     * breakdown gives the wrong answer: a campaign whose ad sets span two pages
     * belongs in both of their buckets, so summing them counts that campaign
     * twice. Counting distinct campaigns per day, with no group in the GROUP BY,
     * is the only reading that can't double up.
     *
     * @return Collection<string, int> day => campaigns
     */
    private function dailyCampaignTotals(array $accountIds, Carbon $start, Carbon $end, string $view, array $selected = [])
    {
        return $this->baseQuery($accountIds, $start, $end, $view, $selected)
            ->groupBy(DB::raw('DATE(meta_ads_campaigns.start_time)'))
            ->get([
                DB::raw('DATE(meta_ads_campaigns.start_time) AS day'),
                DB::raw('COUNT(DISTINCT meta_ads_campaigns.id) AS total'),
            ])
            ->mapWithKeys(fn ($row) => [(string) $row->day => (int) $row->total]);
    }

    /**
     * The id and name the rows are grouped by, chosen by $view.
     *
     * The page view groups by `pages.id`, not by `meta_ads_sets.meta_page_id`:
     * an ad set can carry a page id that matches no row in `pages` (a page from
     * outside this Artemis workspace, or one never synced), and grouping by the
     * raw id gave those their own bucket with a NULL name — which the frontend
     * then labelled "Unassigned page" while the filter, which looks for a NULL
     * id, could never select it. Reading the id back off the joined page makes
     * every unresolvable page land in the one unassigned bucket, so what is
     * shown, what is listed in the legend and what the filter matches agree.
     *
     * Both are fixed identifiers chosen by $view, never user input — safe to
     * inline into raw SQL.
     *
     * @return array{0: string, 1: string}
     */
    private function groupColumns(string $view): array
    {
        return $view === 'user'
            ? ['users.id', 'users.name']
            : ['pages.id', 'pages.name'];
    }

    /**
     * The month's campaigns, joined out to their page (and owner) and narrowed
     * to the visible accounts and the chosen filter. Returned unexecuted — the
     * breakdown and the day totals are the same rows read at two grains.
     */
    private function baseQuery(array $accountIds, Carbon $start, Carbon $end, string $view, array $selected = [])
    {
        [$idColumn] = $this->groupColumns($view);

        $query = Campaign::query()
            ->leftJoin('meta_ads_sets', 'meta_ads_sets.meta_ads_campaign_id', '=', 'meta_ads_campaigns.id')
            ->leftJoin('pages', 'pages.id', '=', 'meta_ads_sets.meta_page_id');

        if ($view === 'user') {
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
            });
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

        return $this->withUnassignedOption($options, $accountIds, $start, $end, 'page', 'Unassigned page');
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

        return $this->withUnassignedOption($options, $accountIds, $start, $end, 'user', 'Unassigned owner');
    }

    /**
     * Appends the unassigned bucket to a filter list, but only when the visible
     * month actually contains campaigns that land in it.
     *
     * @param  array<int, array{id: string, name: string}>  $options
     * @return array<int, array{id: string, name: string}>
     */
    private function withUnassignedOption(array $options, array $accountIds, Carbon $start, Carbon $end, string $view, string $label): array
    {
        if (! empty($accountIds) && $this->hasUnassignedCampaigns($accountIds, $start, $end, $view)) {
            $options[] = ['id' => 'none', 'name' => $label];
        }

        return $options;
    }

    /**
     * Whether the month has any campaign that lands in the unassigned bucket,
     * within the visible accounts. Drives whether the "Unassigned" option is
     * offered at all.
     *
     * It asks the same question the grouping does — is the resolved id null? —
     * so the option appears exactly when there is something for it to select.
     * Checking `meta_ads_sets.meta_page_id IS NULL` instead would miss the two
     * cases that reach the bucket without a null page id: an ad set naming a
     * page this Artemis doesn't know, and (in the owner view) a page that has
     * no owner.
     */
    private function hasUnassignedCampaigns(array $accountIds, Carbon $start, Carbon $end, string $view): bool
    {
        [$idColumn] = $this->groupColumns($view);

        return $this->baseQuery($accountIds, $start, $end, $view)
            ->whereNull($idColumn)
            ->exists();
    }

    /**
     * Calendar payload keyed by `Y-m-d`, each day holding its grand total and a
     * per-group breakdown sorted by volume. Days with no campaigns are omitted (the
     * frontend renders the full grid and treats missing keys as empty).
     *
     * The total comes from $dayTotals rather than from the breakdown: the groups
     * can overlap (one campaign, ad sets on two pages) and adding them up counts
     * such a campaign once per page.
     *
     * @return array<string, array{total: int, groups: array<int, array{id: string, name: string, count: int}>}>
     */
    private function buildDays($rows, $dayTotals, string $unassignedLabel): array
    {
        $days = [];

        foreach ($rows as $row) {
            $day = (string) $row->day;

            if (! isset($days[$day])) {
                // The headline is the day's own distinct-campaign count, not the
                // sum of the groups below it — a campaign running on two pages
                // appears in both breakdowns but started only once.
                $days[$day] = ['total' => (int) ($dayTotals[$day] ?? 0), 'groups' => []];
            }

            $count = (int) $row->total;
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
