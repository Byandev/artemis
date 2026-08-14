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
     * Ads Calendar — a month grid showing how many campaigns START on each day,
     * broken down per Facebook page. Campaigns carry no page of their own,
     * so the page is derived from their ad sets' meta_page_id; a Pancake page's
     * primary key IS that FB page id, so we join straight to `pages` for the name.
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

        // Optional page-owner filter. Narrows to campaigns whose resolved page
        // belongs to one of these members; "Unassigned" has no owner, so it
        // never survives an owner filter.
        $selectedPageOwners = array_values(array_filter(
            array_map('intval', (array) $request->query('page_owners', [])),
            fn ($id) => $id > 0,
        ));

        $pageOptions = $this->pageOptions($workspace, $request->user(), $accountIds->all(), $start, $end);
        $pageOwnerOptions = $this->pageOwnerOptions($workspace, $request->user());

        $rows = $accountIds->isEmpty()
            ? collect()
            : $this->dailyCampaignCountsPerPage($accountIds->all(), $start, $end, $selectedPages, $selectedPageOwners);

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
            'pageOwnerOptions' => $pageOwnerOptions,
            'selectedPageOwners' => array_map('strval', $selectedPageOwners),
        ]);
    }

    /**
     * One row per (day, page) with the number of distinct campaigns. The day is
     * the campaign's start_time (not created_time); the page comes from the
     * campaign's ad sets (LEFT JOIN so a campaign with no ad set / no known page
     * still surfaces under "Unassigned"). A campaign spanning several pages is
     * counted once per page (COUNT DISTINCT dedupes within a page bucket).
     */
    private function dailyCampaignCountsPerPage(array $accountIds, Carbon $start, Carbon $end, array $selectedPages = [], array $selectedPageOwners = [])
    {
        return Campaign::query()
            ->leftJoin('meta_ads_sets', 'meta_ads_sets.meta_ads_campaign_id', '=', 'meta_ads_campaigns.id')
            ->leftJoin('pages', 'pages.id', '=', 'meta_ads_sets.meta_page_id')
            ->whereIn('meta_ads_campaigns.meta_ads_account_id', $accountIds)
            ->whereNotNull('meta_ads_campaigns.start_time')
            ->whereBetween('meta_ads_campaigns.start_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
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
            // Owner scope. An inner condition on pages.owner_id, so campaigns
            // with no resolvable page drop out — they have no owner to match.
            ->when(! empty($selectedPageOwners), fn ($q) => $q->whereIn('pages.owner_id', $selectedPageOwners))
            ->groupBy(DB::raw('DATE(meta_ads_campaigns.start_time)'), 'meta_ads_sets.meta_page_id', 'pages.name')
            ->orderBy(DB::raw('DATE(meta_ads_campaigns.start_time)'))
            ->get([
                DB::raw('DATE(meta_ads_campaigns.start_time) AS day'),
                'meta_ads_sets.meta_page_id',
                'pages.name AS page_name',
                DB::raw('COUNT(DISTINCT meta_ads_campaigns.id) AS total'),
            ]);
    }

    /**
     * Owners of the pages this member can see. Only members who actually own a
     * visible page are listed, so the filter can never return an empty calendar
     * by offering someone with nothing to show.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function pageOwnerOptions(Workspace $workspace, User $user): array
    {
        return Page::query()
            ->where('workspace_id', $workspace->id)
            ->visibleTo($user, $workspace)
            ->whereNotNull('owner_id')
            ->with('owner:id,name')
            ->get(['id', 'owner_id'])
            ->pluck('owner')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->map(fn ($owner) => [
                'id' => (string) $owner->id,
                'name' => $owner->name ?: 'Unnamed member',
            ])
            ->values()
            ->all();
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

        if (! empty($accountIds) && $this->hasUnassignedCampaigns($accountIds, $start, $end)) {
            $options[] = ['id' => 'none', 'name' => 'Unassigned page'];
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
     * per-page breakdown sorted by volume. Days with no campaigns are omitted (the
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
