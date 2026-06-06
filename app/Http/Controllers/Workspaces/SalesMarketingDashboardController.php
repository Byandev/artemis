<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesMarketingDashboardController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        return Inertia::render('workspaces/sales-marketing/dashboard', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
        ]);
    }
}
