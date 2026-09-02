<?php

namespace App\Http\Controllers\Workspaces\SalesMarketing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

/**
 * New Creatives Tracker — a sibling page of the Sales & Marketing group.
 *
 * Scaffold: it renders the page shell (header + divider) and nothing else yet.
 * It still carries the group's gating — the module flag plus its own grant —
 * so the permission is in place before there is anything to protect.
 */
class NewCreativesTrackerController extends Controller
{
    use AuthorizesRequests;

    public function index(Workspace $workspace): Response
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewNewCreativesTracker->value, $workspace);

        return Inertia::render('workspaces/sales-marketing/new-creatives-tracker/index', [
            'workspace' => $workspace,
        ]);
    }
}
