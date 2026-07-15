<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamAdSpendGoal;
use App\Models\Workspace;
use App\Queries\TeamAdSpendGoalStatusQuery;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamAdSpendGoalController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewAdSpendGoals->value, $workspace);

        $goals = TeamAdSpendGoal::where('workspace_id', $workspace->id)
            ->with('team:id,name')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        // Compute status only for the current page's goals, then map each row.
        $statuses = (new TeamAdSpendGoalStatusQuery($workspace))
            ->statuses(collect($goals->items()));

        $goals->getCollection()->transform(
            fn (TeamAdSpendGoal $goal) => $this->presentGoal($goal, $statuses[$goal->id]),
        );

        $teams = Team::ofWorkspace($workspace)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('workspaces/ad-spend-goals/index', [
            'workspace' => $workspace,
            'goals' => $goals,
            'teams' => $teams,
            'canManage' => $request->user()->hasPermission(Permission::ManageAdSpendGoals->value, $workspace),
        ]);
    }

    public function show(Request $request, Workspace $workspace, TeamAdSpendGoal $goal)
    {
        $this->authorize(Permission::ViewAdSpendGoals->value, $workspace);

        $this->guardOwnership($workspace, $goal);

        $goal->load('team:id,name');

        $status = (new TeamAdSpendGoalStatusQuery($workspace))->statusFor($goal);

        $teams = Team::ofWorkspace($workspace)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('workspaces/ad-spend-goals/show', [
            'workspace' => $workspace,
            'goal' => $this->presentGoal($goal, $status),
            'teams' => $teams,
            'canManage' => $request->user()->hasPermission(Permission::ManageAdSpendGoals->value, $workspace),
        ]);
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function presentGoal(TeamAdSpendGoal $goal, array $status): array
    {
        return [
            'id' => $goal->id,
            'team_id' => $goal->team_id,
            'team_name' => $goal->team?->name,
            'daily_target' => (float) $goal->daily_target,
            'start_date' => $goal->start_date->toDateString(),
            'end_date' => $goal->end_date->toDateString(),
            'status' => $status,
        ];
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ManageAdSpendGoals->value, $workspace);

        $validated = $this->validateGoal($request, $workspace);

        TeamAdSpendGoal::create([
            'workspace_id' => $workspace->id,
            ...$validated,
        ]);

        return redirect()->back()->with('success', 'Ad spend goal created successfully.');
    }

    public function update(Request $request, Workspace $workspace, TeamAdSpendGoal $goal)
    {
        $this->authorize(Permission::ManageAdSpendGoals->value, $workspace);

        $this->guardOwnership($workspace, $goal);

        $validated = $this->validateGoal($request, $workspace);

        $goal->update($validated);

        return redirect()->back()->with('success', 'Ad spend goal updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, TeamAdSpendGoal $goal)
    {
        $this->authorize(Permission::ManageAdSpendGoals->value, $workspace);

        $this->guardOwnership($workspace, $goal);

        $goal->delete();

        // Redirect to the index (not back) so deleting from the detail page,
        // whose URL no longer resolves, doesn't 404.
        return redirect()
            ->route('workspaces.ad-spend-goals.index', $workspace)
            ->with('success', 'Ad spend goal deleted successfully.');
    }

    /**
     * @return array{team_id: int, daily_target: float, start_date: string, end_date: string}
     */
    private function validateGoal(Request $request, Workspace $workspace): array
    {
        return $request->validate([
            'team_id' => [
                'required',
                Rule::exists('teams', 'id')->where('workspace_id', $workspace->id),
            ],
            'daily_target' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
    }

    private function guardOwnership(Workspace $workspace, TeamAdSpendGoal $goal): void
    {
        if ($goal->workspace_id !== $workspace->id) {
            abort(403, 'This goal does not belong to the current workspace.');
        }
    }
}
