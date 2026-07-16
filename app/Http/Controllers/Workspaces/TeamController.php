<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Workspace;
use App\Rules\DiscordWebhookUrl;
use App\Services\PostHogService;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewTeams->value, $workspace);

        $teams = QueryBuilder::for(
            Team::ofWorkspace($workspace)
                ->when(
                    ! TeamVisibility::isUnrestricted($request->user(), $workspace),
                    fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $request->user()->id)),
                )
                ->withCount('members')
                ->with(['members:id,name,email'])
        )
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
        $this->authorize(Permission::CreateTeams->value, $workspace);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')->where(function ($query) use ($workspace) {
                    return $query->where('workspace_id', $workspace->id);
                }),
            ],
            'discord_webhook_url' => ['nullable', 'string', 'max:512', new DiscordWebhookUrl],
            'members' => ['array'],
            'members.*' => ['exists:users,id'],
        ]);

        $team = Team::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'discord_webhook_url' => $validated['discord_webhook_url'] ?? null,
        ]);

        if (! empty($validated['members'])) {
            $validMemberIds = $workspace->users()
                ->whereIn('users.id', $validated['members'])
                ->pluck('users.id');

            $team->members()->attach($validMemberIds);
        }

        (new PostHogService)->capture((string) $request->user()->id, 'team_created', [
            'workspace_id' => $workspace->id,
            'team_id' => $team->id,
            'team_name' => $team->name,
            'members_count' => count($validated['members'] ?? []),
        ]);

        return redirect()->back()->with('success', 'Team created successfully.');
    }

    public function update(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::EditTeams->value, $workspace);

        if ($team->workspace_id !== $workspace->id) {
            abort(403, 'This team does not belong to the current workspace.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'discord_webhook_url' => ['nullable', 'string', 'max:512', new DiscordWebhookUrl],
            'members' => ['array'],
            'members.*' => ['exists:users,id'],
        ]);

        $team->update([
            'name' => $validated['name'],
            'discord_webhook_url' => $validated['discord_webhook_url'] ?? null,
        ]);

        $validMemberIds = $workspace->users()
            ->whereIn('users.id', $validated['members'] ?? [])
            ->pluck('users.id');

        $team->members()->sync($validMemberIds);

        return redirect()->back()->with('success', 'Team updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::DeleteTeams->value, $workspace);

        if ($team->workspace_id !== $workspace->id) {
            abort(403, 'This team does not belong to the current workspace.');
        }

        $team->delete();

        return redirect()->back()->with('success', 'Team deleted successfully.');
    }
}
