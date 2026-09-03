<?php

namespace App\Http\Controllers\Workspaces\SalesMarketing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales & Marketing Dashboard — the group's landing page. Laid out like the main
 * dashboard: a header carrying the date range, then the KPI grid. The KPIs fetch
 * themselves (see SalesMarketingDashboardController in API\Workspace), and team
 * narrowing comes from the workspace-wide switcher — so this only renders the
 * shell.
 */
class DashboardController extends Controller
{
    use AuthorizesRequests;

    public function index(Workspace $workspace): Response
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        return Inertia::render('workspaces/sales-marketing/dashboard/index', [
            'workspace' => $workspace,
        ]);
    }
}
