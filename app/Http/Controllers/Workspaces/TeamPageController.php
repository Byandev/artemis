<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamPageController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::ViewTeams->value, $workspace);
        $this->ensureTeamBelongsToWorkspace($team, $workspace);

        return Inertia::render('workspaces/teams/pages', [
            'workspace' => $workspace,
            'team' => $team->only(['id', 'name']),
            'pages' => Page::ofWorkspace($workspace)->orderBy('name')->get(['id', 'name']),
            'assignedPageIds' => $team->pages()->pluck('pages.id'),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::EditTeams->value, $workspace);
        $this->ensureTeamBelongsToWorkspace($team, $workspace);

        $validated = $request->validate([
            'page_ids' => ['array'],
            'page_ids.*' => ['integer'],
        ]);

        // Only sync pages that actually belong to this workspace.
        $validPageIds = Page::ofWorkspace($workspace)
            ->whereIn('id', $validated['page_ids'] ?? [])
            ->pluck('id');

        $team->pages()->sync($validPageIds);

        return redirect()->back()->with('success', 'Team pages updated successfully.');
    }

    private function ensureTeamBelongsToWorkspace(Team $team, Workspace $workspace): void
    {
        if ($team->workspace_id !== $workspace->id) {
            abort(403, 'This team does not belong to the current workspace.');
        }
    }
}
