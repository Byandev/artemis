<?php

namespace App\Http\Controllers\Workspaces\SalesMarketing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Queries\AdvertiserDashboardQuery;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily Report — advertiser performance, one of the five sibling pages under
 * Sales & Marketing. It grew out of the gencys intern dashboard and still shares
 * that query; the route, permission and module gate are S&M's own.
 */
class DailyReportController extends Controller
{
    use AuthorizesRequests;

    /** Inertia page shell + first paint, on the default date. */
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorizeAccess($workspace);

        [$data, $filters] = $this->build($request, $workspace);

        return Inertia::render('workspaces/sales-marketing/daily-report/index', [
            'workspace' => $workspace,
            'view' => $data,
            'filters' => $filters,
            'baseUrl' => "/workspaces/{$workspace->slug}/sales-marketing/daily-report",
        ]);
    }

    /** JSON data endpoint the page fetches (axios) when the date changes. */
    public function data(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeAccess($workspace);

        [$data, $filters] = $this->build($request, $workspace);

        return response()->json([
            'view' => $data,
            'filters' => $filters,
        ]);
    }

    private function authorizeAccess(Workspace $workspace): void
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDailyReport->value, $workspace);
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function build(Request $request, Workspace $workspace): array
    {
        $date = $request->input('filter.date') ?: null;

        // Pass the viewer so the query applies team visibility (scoped users see
        // only their team's advertisers; the "viewing as team" switcher narrows).
        $data = (new AdvertiserDashboardQuery($workspace, [], $date, $request->user()))->get();

        return [$data, ['date' => $data['date']]];
    }
}
