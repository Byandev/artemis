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
