<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;

class InventoryDashboardController extends Controller
{
    use AuthorizesRequests;

    /**
     * Renders the dashboard shell only. Every widget fetches its own statistic
     * from a dedicated browser-API endpoint (see InventoryDashboardStatsController),
     * so the page paints instantly and each panel loads, skeletons and refreshes
     * on its own.
     */
    public function index(Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        return Inertia::render('workspaces/inventory/dashboard/index', [
            'workspace' => $workspace,
        ]);
    }
}
