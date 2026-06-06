<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VideoEditorDashboardController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewVideoEditorDashboard->value, $workspace);

        return Inertia::render('workspaces/video-editor/dashboard', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
        ]);
    }
}
