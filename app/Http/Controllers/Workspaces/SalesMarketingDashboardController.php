<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Queries\AdvertiserDashboardQuery;
use App\Support\SalesMarketingDashboard;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Sales & Marketing dashboard is the intern performance dashboard — it
 * renders the same page (and data) as the gencys intern dashboard, but under
 * the S&M module's own route, permission and gating.
 *
 * The dashboard is tabbed and each tab is its own URL (route path), e.g.
 * `.../dashboard` → Daily Report. New tabs slot into {@see self::tabs()}.
 */
class SalesMarketingDashboardController extends Controller
{
    use AuthorizesRequests;

    /** Inertia page shell + first paint (default date) — the Daily Report tab. */
    public function index(Request $request, Workspace $workspace, ?string $tab = null): Response
    {
        $this->authorizeAccess($workspace);

        // Only the Daily Report tab is served here; other tabs (e.g. the Page
        // ROAS Tracker) have their own routes. Reject unknown segments.
        abort_unless(in_array($tab ?: 'daily-report', ['daily-report'], true), 404);

        [$data, $filters] = $this->build($request, $workspace);

        return Inertia::render('workspaces/gencys/intern-dashboard/index', [
            'workspace' => $workspace,
            'view' => $data,
            'filters' => $filters,
            'baseUrl' => "/workspaces/{$workspace->slug}/sales-marketing/dashboard",
            'tabs' => SalesMarketingDashboard::tabs($workspace),
            'activeTab' => 'daily-report',
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

        // Pass the viewer so the query applies team visibility (scoped users see
        // only their team's advertisers; the "viewing as team" switcher narrows).
        $data = (new AdvertiserDashboardQuery($workspace, [], $date, $request->user()))->get();

        return [$data, ['date' => $data['date']]];
    }
}
