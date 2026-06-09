<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\VideoEditorDashboardRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creatives\Models\Creative;

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
            'editors' => $this->editors($workspace, $request->user()),
            'filters' => $request->filters()->toArray(),
        ]);
    }

    /**
     * Editors selectable in the dashboard filter: everyone who has authored a
     * creative in this workspace, plus the signed-in user so they can always
     * see their own (even with zero creatives).
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function editors(Workspace $workspace, User $currentUser)
    {
        $creatorIds = Creative::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('creator_id')
            ->distinct()
            ->pluck('creator_id')
            ->push($currentUser->id)
            ->unique();

        return User::query()
            ->whereIn('id', $creatorIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]);
    }
}
