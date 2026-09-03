<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Metrics\MetricSource;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Page;
use App\Models\Workspace;
use App\Support\AdvertiserVisibility;
use App\Support\TeamVisibility;
use App\Support\WorkspaceMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Per-KPI endpoints for the Sales & Marketing dashboard. Each statistic gets its
 * own request so the frontend can load, skeleton and retry it on its own, the
 * way the inventory and video-editor dashboards already do.
 *
 * Sales comes from the same place the main dashboard's stat cards get theirs —
 * the `totalSales` metric resolved through WorkspaceMetrics — so the two
 * dashboards report the same number for the same window rather than two subtly
 * different definitions of "sales". Ad spend comes from the rows the group's own
 * Ad Spent Summary sums, for the same reason. Blended ROAS divides those two, so
 * the ratio always matches the two figures shown beside it on the page, and
 * carries the attributed ROAS alongside for comparison.
 *
 * The window arrives from the page, which owns it. Team narrowing does not: the
 * workspace-wide "viewing as team" switcher resolves per request, so this only
 * validates the window, applies that visibility, and answers with the number.
 */
class SalesMarketingDashboardController extends Controller
{
    use AuthorizesRequests;

    /** Total sales for the window the page asked for. */
    public function totalSales(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        return response()->json([
            'value' => $this->salesIn($request, $workspace, $this->window($request)),
        ]);
    }

    /** Total ad spend for the window the page asked for. */
    public function totalAdSpend(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        return response()->json([
            'value' => $this->adSpendIn($request, $workspace, $this->window($request)),
        ]);
    }

    /**
     * Blended ROAS for the window — every peso of sales over every peso of ad
     * spend: the same two figures the cards beside it report, divided, so the
     * ratio always matches them.
     *
     * `actual` is the attributed ROAS over the same window: the sales the ad
     * platform credits to the ads, over the same spend. It is the ratio the Ad
     * Spent Summary reports, and it sits beside the blended figure because the
     * gap between the two is the point — blended counts sales the ads were never
     * credited with.
     *
     * Both are null when there was no spend: there is no ratio to state against
     * zero, and reporting 0 would read as "terrible" rather than "not applicable".
     */
    public function blendedRoas(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $window = $this->window($request);

        $adSpend = $this->adSpendIn($request, $workspace, $window);

        return response()->json([
            'value' => $adSpend > 0
                ? round($this->salesIn($request, $workspace, $window) / $adSpend, 2)
                : null,
            'actual' => $adSpend > 0
                ? round($this->attributedSalesIn($request, $workspace, $window) / $adSpend, 2)
                : null,
        ]);
    }

    /**
     * RTS rate for the window, as a 0–1 ratio — the share of orders that reached
     * a delivery outcome and came back rather than landing. This is the
     * `rtsRate` metric, so it agrees with the main dashboard's card of the same
     * name.
     *
     * `returning_amount` is the money behind that rate: the pesos that entered
     * the return journey over the same window, which is the numerator the rate
     * is built from. A percentage on its own does not say how much is at stake.
     */
    public function rtsRate(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $metrics = $this->metrics($request, $workspace, $this->window($request))
            ->extract(['rtsRate', 'returningAmount']);

        return response()->json([
            'value' => (float) $metrics['rtsRate'],
            'returning_amount' => (float) $metrics['returningAmount'],
        ]);
    }

    /**
     * The advertiser who spent the most over the window, with what that spend
     * bought: their attributed sales, and the workspace's whole spend so the
     * card can state their share of it.
     *
     * `advertiser` is null when nothing was spent at all; the card says so
     * rather than showing a leader of nothing.
     */
    public function highestAdSpend(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $window = $this->window($request);
        $leader = $this->topAdvertiser($request, $workspace, $window, 'ad_spent', ['sales']);

        return response()->json([
            'advertiser' => $this->advertiserOf($leader),
            'value' => (float) ($leader->value ?? 0),
            'total' => $this->adSpendIn($request, $workspace, $window),
            // The card divides this by the spend for the ROAS it states.
            'sales' => (float) ($leader->sales ?? 0),
        ]);
    }

    /**
     * The advertiser whose ads were credited with the most sales over the
     * window, with the order count behind it and the workspace's whole
     * attributed sales for the share.
     */
    public function highestSales(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $window = $this->window($request);
        $leader = $this->topAdvertiser($request, $workspace, $window, 'sales', ['orders']);

        return response()->json([
            'advertiser' => $this->advertiserOf($leader),
            'value' => (float) ($leader->value ?? 0),
            'total' => $this->attributedSalesIn($request, $workspace, $window),
            // The card divides the sales by this for the AOV it states.
            'orders' => (int) ($leader->orders ?? 0),
        ]);
    }

    /**
     * The advertiser who turned spend into sales most efficiently over the
     * window — the highest sales-to-spend ratio, not the largest number.
     *
     * Only advertisers who actually spent are eligible; a ratio over no spend
     * is not efficiency, it is a division by zero. There is deliberately no
     * minimum-spend floor beyond that, so this reports the literal highest
     * ROAS — a small spend that happened to convert can top it.
     *
     * Unlike the other leaders there is no "share of total" to state: a ratio
     * is not a slice of anything, so the card labels it as efficiency instead.
     */
    public function highestRoas(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $leader = $this->advertiserRecords($request, $workspace, $this->window($request))
            ->groupBy('advertiser_id', 'advertiser_name')
            ->selectRaw('
                advertiser_id,
                advertiser_name,
                ROUND(SUM(sales) / NULLIF(SUM(ad_spent), 0), 2) AS value,
                SUM(ad_spent) AS ad_spend,
                SUM(sales) AS sales
            ')
            ->havingRaw('SUM(ad_spent) > 0')
            ->orderByDesc('value')
            ->first();

        return response()->json([
            'advertiser' => $this->advertiserOf($leader),
            'value' => (float) ($leader->value ?? 0),
            // The card states both sides of the ratio underneath it.
            'ad_spend' => (float) ($leader->ad_spend ?? 0),
            'sales' => (float) ($leader->sales ?? 0),
        ]);
    }

    /**
     * The advertiser whose parcels came back least often over the window — the
     * lowest share of delivery outcomes that ended in a return.
     *
     * Rate is computed from the amounts rather than averaged off the stored
     * per-day `rts_rate`: a mean of daily rates weights a quiet day the same as
     * a busy one. Only advertisers with an outcome either way are eligible —
     * nothing delivered and nothing returned is not a perfect record.
     */
    public function lowestRts(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $leader = $this->advertiserRecords($request, $workspace, $this->window($request))
            ->groupBy('advertiser_id', 'advertiser_name')
            ->selectRaw('
                advertiser_id,
                advertiser_name,
                ROUND(
                    SUM(returned_amount) / NULLIF(SUM(returned_amount + delivered_amount), 0),
                    4
                ) AS value,
                SUM(returned_amount) AS returned_amount
            ')
            ->havingRaw('SUM(returned_amount + delivered_amount) > 0')
            ->orderBy('value')
            ->first();

        return response()->json([
            // A zero rate is the best possible result here, not an absent one,
            // so this leader is judged on having an outcome at all.
            'advertiser' => $leader
                ? ['id' => (int) $leader->advertiser_id, 'name' => $leader->advertiser_name]
                : null,
            'value' => (float) ($leader->value ?? 0),
            'returned_amount' => (float) ($leader->returned_amount ?? 0),
        ]);
    }

    /**
     * Every advertiser's raw figures for the window, one row each — what the
     * team-member comparison plots.
     *
     * Deliberately just sums: no ratios, no ranking, no average, no comparison
     * against another window. Which metric is being compared is a choice the
     * panel makes after the data has arrived (it switches between Sales, Ad
     * spend, ROAS and RTS without refetching), so deriving any of it here would
     * be computing something the page might not use — and would need redoing
     * per metric anyway. The panel calls this twice, once per window, and works
     * out the rest itself.
     */
    public function teamComparison(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        return response()->json([
            'rows' => $this->advertiserRows($request, $workspace, $this->window($request)),
        ]);
    }

    /**
     * The same per-advertiser rows, for the breakdown table beneath the chart.
     *
     * Its own endpoint so the table loads, skeletons and refreshes on its own
     * rather than waiting on the chart, but deliberately the same query: the
     * table states what the chart plots, and two implementations of one figure
     * is how the two would eventually disagree.
     *
     * Totals are the page's job, not this one's — the sub-total row's blended
     * ROAS and overall RTS are divisions of these sums, computed alongside every
     * other ratio on the dashboard.
     */
    public function teamBreakdown(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        return response()->json([
            'rows' => $this->advertiserRows($request, $workspace, $this->window($request)),
        ]);
    }

    /**
     * One row of raw sums per advertiser over the window.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function advertiserRows(Request $request, Workspace $workspace, array $window)
    {
        return $this->advertiserRecords($request, $workspace, $window)
            ->groupBy('advertiser_id', 'advertiser_name')
            ->selectRaw('
                advertiser_id,
                advertiser_name,
                SUM(ad_spent) AS ad_spend,
                SUM(sales) AS sales,
                SUM(orders) AS orders,
                SUM(returned_amount) AS returned_amount,
                SUM(delivered_amount) AS delivered_amount
            ')
            // By name, not by size: the callers sort on whichever metric is
            // selected, and a server-side ranking would only ever be one of them.
            ->orderBy('advertiser_name')
            ->get()
            ->map(fn ($row) => [
                'advertiser' => [
                    'id' => (int) $row->advertiser_id,
                    'name' => $row->advertiser_name,
                ],
                'ad_spend' => (float) $row->ad_spend,
                'sales' => (float) $row->sales,
                'orders' => (int) $row->orders,
                'returned_amount' => (float) $row->returned_amount,
                'delivered_amount' => (float) $row->delivered_amount,
            ]);
    }

    /**
     * The advertiser topping $column over the window, with any $also columns
     * summed alongside it. Raw sums only — every ratio the cards show (share,
     * ROAS, AOV) is a division they do themselves, the way the KPI cards work
     * out their own trend.
     *
     * $column and $also are this class's own literals, never request input.
     *
     * @param  list<string>  $also
     */
    private function topAdvertiser(Request $request, Workspace $workspace, array $window, string $column, array $also = []): ?object
    {
        $sums = collect(["SUM({$column}) AS value"])
            ->merge(array_map(fn (string $c) => "SUM({$c}) AS {$c}", $also))
            ->implode(', ');

        return $this->advertiserRecords($request, $workspace, $window)
            ->groupBy('advertiser_id', 'advertiser_name')
            ->selectRaw("advertiser_id, advertiser_name, {$sums}")
            ->orderByDesc('value')
            ->first();
    }

    /**
     * The leader's identity, or null when they led on nothing — a workspace
     * with no spend has no biggest spender.
     *
     * @return array{id: int, name: ?string}|null
     */
    private function advertiserOf(?object $leader): ?array
    {
        return $leader && $leader->value > 0
            ? ['id' => (int) $leader->advertiser_id, 'name' => $leader->advertiser_name]
            : null;
    }

    /** The `totalSales` metric over the window — the main dashboard's own path. */
    private function salesIn(Request $request, Workspace $workspace, array $window): float
    {
        return (float) $this->metrics($request, $workspace, $window)
            ->extract(['totalSales'])['totalSales'];
    }

    /**
     * WorkspaceMetrics over the window, scoped to this viewer — the same object
     * the main dashboard's analytics endpoint resolves its cards from.
     */
    private function metrics(Request $request, Workspace $workspace, array $window): WorkspaceMetrics
    {
        return $workspace->metrics(
            ['start_date' => $window['start'], 'end_date' => $window['end']],
            $this->visibilityFilter($request, $workspace),
            MetricSource::normalize($request->input('source')),
        );
    }

    /**
     * Attributed sales over the window — the `sales` the ad platform credits to
     * the ads, carried on the same advertiser rows as the spend.
     */
    private function attributedSalesIn(Request $request, Workspace $workspace, array $window): float
    {
        return (float) $this->advertiserRecords($request, $workspace, $window)->sum('sales');
    }

    /** Ad spend over the window, from the rows the Ad Spent Summary sums. */
    private function adSpendIn(Request $request, Workspace $workspace, array $window): float
    {
        return (float) $this->advertiserRecords($request, $workspace, $window)->sum('ad_spent');
    }

    /**
     * The advertiser daily rows for this window, scoped to what the viewer may
     * see. Both ad spend and attributed sales are summed off this one query.
     */
    private function advertiserRecords(Request $request, Workspace $workspace, array $window): Builder
    {
        // Gencys partners read the Gencys pipeline's rows (advertiser = Intern);
        // everyone else reads the Artemis rows (advertiser = User). Same split
        // the Ad Spent Summary makes, so the two pages agree.
        $source = $workspace->is_gencys_partner
            ? AdvertiserPerformanceDailyRecord::SOURCE_GENCYS
            : AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS;

        // Null = no restriction; an empty set yields whereIn(..., []) → no rows,
        // which is the fail-closed answer for a scoped viewer who sees nobody.
        $visibleIds = AdvertiserVisibility::visibleIds($request->user(), $workspace, $source);

        return AdvertiserPerformanceDailyRecord::query()
            ->where('workspace_id', $workspace->id)
            ->where('source', $source)
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('advertiser_id', $visibleIds))
            ->whereBetween('date', [$window['start'], $window['end']]);
    }

    /**
     * The window the page asked for. Validated rather than parsed: the dates go
     * straight into a date range, so they have to be dates and they have to be
     * the right way round.
     *
     * @return array{start: string, end: string}
     */
    private function window(Request $request): array
    {
        return $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);
    }

    /**
     * The metric filter, built entirely from what this viewer may see: their own
     * team(s) if they are scoped, and whatever the "viewing as team" switcher is
     * set to — which narrows unrestricted users too. An empty array means no
     * narrowing applies, so the figure covers the whole workspace.
     */
    private function visibilityFilter(Request $request, Workspace $workspace): array
    {
        $user = $request->user();

        if (! $user || ! TeamVisibility::shouldScope($user, $workspace)) {
            return [];
        }

        // Fail-closed: a scoped viewer with nothing visible must match no pages,
        // not fall through to every page in the workspace.
        $visiblePageIds = Page::where('workspace_id', $workspace->id)
            ->visibleTo($user, $workspace)
            ->pluck('id')
            ->all();

        return ['page_ids' => empty($visiblePageIds) ? [-1] : $visiblePageIds];
    }
}
