<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Queries\AdvertiserDashboardQuery;

/**
 * The Sales & Marketing dashboard is the intern performance dashboard — it
 * renders the same page (and data) as the gencys intern dashboard, but under
 * the S&M module's own route, permission and gating.
 */
class SalesMarketingDashboardController extends Controller
{
    use AuthorizesRequests;

    /** Inertia page shell + first paint (default date). */
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorizeAccess($workspace);

        [$data, $filters] = $this->build($request, $workspace);

        return Inertia::render('workspaces/gencys/intern-dashboard/index', [
            'workspace' => $workspace,
            'view' => $data,
            'filters' => $filters,
            'baseUrl' => "/workspaces/{$workspace->slug}/sales-marketing/dashboard",
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

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function build(Request $request, Workspace $workspace): array
    {
        $date = $request->input('filter.date') ?: null;

        $data = (new AdvertiserDashboardQuery($workspace, [], $date))->get();

        return [$data, ['date' => $data['date']]];
    }
}
