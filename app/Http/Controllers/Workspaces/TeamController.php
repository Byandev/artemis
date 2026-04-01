<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        $query = Team::ofWorkspace($workspace)
            ->withCount('members')
            ->with(['members:id,name,email']);

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSortFields = ['name', 'created_at', 'members_count'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        // Pagination
        $teams = $query->paginate(10)->withQueryString();

        // Get workspace members for the dropdown
        $workspaceMembers = $workspace->users()
            ->select('users.id', 'users.name', 'users.email')
            ->get();

        $isAdmin = $request->user()->isAdminOf($workspace);

        return Inertia::render('workspaces/teams/index', [
            'workspace' => $workspace,
            'teams' => $teams,
            'workspaceMembers' => $workspaceMembers,
            'isAdmin' => $isAdmin,
            'filters' => [
                'search' => $request->get('search', ''),
                'sort' => $sortField,
                'direction' => $sortDirection,
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
            'name' => ['required', 'string', 'max:255'],
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
