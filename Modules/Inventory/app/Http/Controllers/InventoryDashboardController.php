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
     * Renders the dashboard shell. Widgets are not wired up yet — each one is
     * meant to fetch its own statistic from its own endpoint so the page paints
     * instantly and every panel loads, skeletons and refreshes on its own.
     */
    public function index(Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        return Inertia::render('workspaces/inventory/dashboard/index', [
            'workspace' => $workspace,
        ]);
    }
}
