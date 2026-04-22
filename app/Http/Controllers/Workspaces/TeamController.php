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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Teams', $workspace);

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

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize('Create Teams', $workspace);

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
            $validMemberIds = $workspace->users()
                ->whereIn('users.id', $validated['members'])
                ->pluck('users.id');

            $team->members()->attach($validMemberIds);
        }

        return redirect()->back()->with('success', 'Team created successfully.');
    }

    public function update(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize('Edit Teams', $workspace);

        if ($team->workspace_id !== $workspace->id) {
            abort(403, 'This team does not belong to the current workspace.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'members' => ['array'],
            'members.*' => ['exists:users,id'],
        ]);

        $team->update([
            'name' => $validated['name'],
        ]);

        $validMemberIds = $workspace->users()
            ->whereIn('users.id', $validated['members'] ?? [])
            ->pluck('users.id');

        $team->members()->sync($validMemberIds);

        return redirect()->back()->with('success', 'Team updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize('Delete Teams', $workspace);

        if ($team->workspace_id !== $workspace->id) {
            abort(403, 'This team does not belong to the current workspace.');
        }

        $team->delete();

        return redirect()->back()->with('success', 'Team deleted successfully.');
    }
}