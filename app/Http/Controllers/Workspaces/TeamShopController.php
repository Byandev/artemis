<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamShopController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::ViewTeams->value, $workspace);
        $this->ensureTeamBelongsToWorkspace($team, $workspace);

        return Inertia::render('workspaces/teams/shops', [
            'workspace' => $workspace,
            'team' => $team->only(['id', 'name']),
            'shops' => Shop::where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name']),
            'assignedShopIds' => $team->shops()->pluck('shops.id'),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::EditTeams->value, $workspace);
        $this->ensureTeamBelongsToWorkspace($team, $workspace);

        $validated = $request->validate([
            'shop_ids' => ['array'],
            'shop_ids.*' => ['integer'],
        ]);

        // Only sync shops that actually belong to this workspace.
        $validShopIds = Shop::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['shop_ids'] ?? [])
            ->pluck('id');

        $team->shops()->sync($validShopIds);

        return redirect()->back()->with('success', 'Team shops updated successfully.');
    }

    private function ensureTeamBelongsToWorkspace(Team $team, Workspace $workspace): void
    {
        if ($team->workspace_id !== $workspace->id) {
            abort(403, 'This team does not belong to the current workspace.');
        }
    }
}
