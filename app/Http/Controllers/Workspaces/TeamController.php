<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TeamController extends Controller
{
    /**
     * Display a listing of teams.
     */
    public function index(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $teams = QueryBuilder::for(Team::ofWorkspace($workspace)->withCount('members')->with(['members:id,name,email']))
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'created_at', 'members_count'])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $workspaceMembers = $workspace->users()
            ->select('users.id', 'users.name', 'users.email')
            ->get();

        $isAdmin = $request->user()->isAdminOf($workspace);

        return Inertia::render('workspaces/teams/index', [
            'workspace' => $workspace,
            'teams' => $teams,
            'workspaceMembers' => $workspaceMembers,
            'isAdmin' => $isAdmin,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /**
     * Store a newly created team in storage.
     */
    public function store(Request $request, Workspace $workspace)
    {
        // Check if user has admin access
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have permission to create teams.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')->where(function ($query) use ($workspace) {
                    return $query->where('workspace_id', $workspace->id);
                }),
            ],
            'members' => ['array'],
            'members.*' => ['exists:users,id'],
        ]);

        $team = Team::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
        ]);

        if (! empty($validated['members'])) {
            // Validate that all selected members are part of the workspace
            $validMemberIds = $workspace->users()
                ->whereIn('users.id', $validated['members'])
                ->pluck('users.id');

            $team->members()->attach($validMemberIds);
        }

        return redirect()->back()->with('success', 'Team created successfully.');
    }

    /**
     * Update the specified team in storage.
     */
    public function update(Request $request, Workspace $workspace, Team $team)
    {
        // Check if user has admin access
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have permission to update teams.');
        }

        // Ensure the team belongs to the workspace
        if ($team->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'members' => ['array'],
            'members.*' => ['exists:users,id'],
        ]);

        $team->update([
            'name' => $validated['name'],
        ]);

        // Validate that all selected members are part of the workspace
        $validMemberIds = $workspace->users()
            ->whereIn('users.id', $validated['members'] ?? [])
            ->pluck('users.id');

        $team->members()->sync($validMemberIds);

        return redirect()->back()->with('success', 'Team updated successfully.');
    }

    /**
     * Remove the specified team from storage.
     */
    public function destroy(Request $request, Workspace $workspace, Team $team)
    {
        // Check if user has admin access
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have permission to delete teams.');
        }

        // Ensure the team belongs to the workspace
        if ($team->workspace_id !== $workspace->id) {
            abort(404);
        }

        $team->delete();

        return redirect()->back()->with('success', 'Team deleted successfully.');
    }
}
