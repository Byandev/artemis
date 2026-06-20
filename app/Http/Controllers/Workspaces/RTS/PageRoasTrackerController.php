<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Queries\PageRoasTrackerQuery;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageRoasTrackerController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewPageRoasTracker->value, $workspace);

        return Inertia::render('workspaces/rts/page-roas-tracker', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            ...(new PageRoasTrackerQuery($workspace, $request->user(), $request))->payload(),
        ]);
    }
}
