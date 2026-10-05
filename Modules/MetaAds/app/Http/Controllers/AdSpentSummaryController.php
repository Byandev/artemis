<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Workspace;
use App\Support\AdvertiserVisibility;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdSpentSummaryController extends Controller
{
    /**
     * Ad Spent ROAS Summary — the page shell. The rows load over XHR from
     * data(), so the page paints before the daily sums are taken. The filter's
     * advertiser options still ship with the page.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorizeAccess($request, $workspace);

        [$start, $end, $selectedAdvertisers] = $this->filters($request);

        $source = $this->source($workspace);
        $visibleIds = AdvertiserVisibility::visibleIds($request->user(), $workspace, $source);

        // Advertiser filter options: the advertisers present in this source,
        // scoped to what the viewer may see (labels are denormalised on the row).
        $advertiserOptions = AdvertiserPerformanceDailyRecord::query()
            ->where('workspace_id', $workspace->id)
            ->where('source', $source)
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('advertiser_id', $visibleIds))
            ->whereNotNull('advertiser_name')
            ->select('advertiser_id', 'advertiser_name')
            ->distinct()
            ->orderBy('advertiser_name')
            ->get()
            ->unique('advertiser_id')
            ->map(fn ($r) => ['value' => (string) $r->advertiser_id, 'label' => $r->advertiser_name])
            ->values()
            ->all();

        return Inertia::render('workspaces/integrations/meta-ad-spent-summary', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'filters' => $this->presentFilters($start, $end, $selectedAdvertisers),
            'advertiserOptions' => $advertiserOptions,
        ]);
    }

    /**
     * One row per day over the selected range with the workspace's total
     * orders, sales, ad spend and ROAS (sales ÷ ad spend), summed across
     * advertisers from advertiser_performance_daily_records. Served from
     * browser-api.php at api/workspaces/{workspace}/sales-marketing/ad-spent-summary.
     */
    public function data(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeAccess($request, $workspace);

        [$start, $end, $selectedAdvertisers] = $this->filters($request);

        $source = $this->source($workspace);

        // Team visibility: scoped users only see their team's advertisers; the
        // "viewing as team" switcher narrows everyone. Null = no restriction; an
        // empty set yields whereIn(..., []) → no rows (fail closed).
        $visibleIds = AdvertiserVisibility::visibleIds($request->user(), $workspace, $source);

        $daily = AdvertiserPerformanceDailyRecord::query()
            ->where('workspace_id', $workspace->id)
            ->where('source', $source)
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('advertiser_id', $visibleIds))
            ->when($selectedAdvertisers, fn ($q) => $q->whereIn('advertiser_id', $selectedAdvertisers))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('date')
            ->get([
                'date',
                DB::raw('COALESCE(SUM(orders), 0) as orders'),
                DB::raw('COALESCE(SUM(sales), 0) as sales'),
                DB::raw('COALESCE(SUM(ad_spent), 0) as ad_spent'),
            ])
            ->keyBy(fn ($r) => $r->date->toDateString());

        // One row per calendar day so days without records still render.
        $rows = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $day = $cursor->toDateString();
            $record = $daily[$day] ?? null;
            $sales = (float) ($record->sales ?? 0);
            $adSpent = (float) ($record->ad_spent ?? 0);

            $rows[] = [
                'date' => $day,
                'orders' => (int) ($record->orders ?? 0),
                'sales' => round($sales, 2),
                'ad_spent' => round($adSpent, 2),
                'roas' => $adSpent > 0 ? round($sales / $adSpent, 2) : null,
            ];
        }

        return response()->json([
            'filters' => $this->presentFilters($start, $end, $selectedAdvertisers),
            'rows' => $rows,
        ]);
    }

    /**
     * One of the Sales & Marketing pages: the S&M module flag still gates it,
     * but the grant is its own rather than the whole group's.
     */
    private function authorizeAccess(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);
        abort_unless(
            $request->user()->hasPermission(Permission::ViewAdSpentSummary->value, $workspace),
            403,
        );
    }

    /**
     * The date range (trailing 7 days by default, swapped if given backwards)
     * and the selected advertiser ids, normalised to ints. The query AND's the
     * selection with the visible set, so a scoped user can't widen it.
     *
     * @return array{0: Carbon, 1: Carbon, 2: array<int, int>}
     */
    private function filters(Request $request): array
    {
        $end = ($request->date('end_date') ?? now())->startOfDay();
        $start = ($request->date('start_date') ?? now()->copy()->subDays(6))->startOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $selectedAdvertisers = collect((array) $request->input('advertisers'))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [$start, $end, $selectedAdvertisers];
    }

    /**
     * @param  array<int, int>  $selectedAdvertisers
     * @return array<string, mixed>
     */
    private function presentFilters(Carbon $start, Carbon $end, array $selectedAdvertisers): array
    {
        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            // String ids so they round-trip into the multi-select's value model.
            'advertisers' => array_map('strval', $selectedAdvertisers),
        ];
    }

    /**
     * Gencys partners read the Gencys pipeline's rows (advertiser = Intern);
     * everyone else reads the Artemis rows (advertiser = User).
     */
    private function source(Workspace $workspace): string
    {
        return $workspace->is_gencys_partner
            ? AdvertiserPerformanceDailyRecord::SOURCE_GENCYS
            : AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS;
    }
}
