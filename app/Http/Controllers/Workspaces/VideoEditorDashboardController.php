<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\VideoEditorDashboardRequest;
use App\Models\Product;
use App\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class VideoEditorDashboardController extends Controller
{
    /**
     * Editor-focused dashboard scoped to the signed-in editor's own creatives.
     *
     * Renders only the shell (filters, product options, identity). Each
     * statistic is fetched independently by the frontend from the matching
     * API\Workspace\VideoEditorDashboardController endpoint, so sections load
     * progressively with their own skeletons.
     */
    public function __invoke(VideoEditorDashboardRequest $request, Workspace $workspace): Response
    {
        return Inertia::render('workspaces/video-editor/dashboard', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'currentUserId' => $request->user()->id,
            'products' => Product::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('title')
                ->get(['id', 'title']),
            'filters' => $request->filters()->toArray(),
        ]);
    }
}
