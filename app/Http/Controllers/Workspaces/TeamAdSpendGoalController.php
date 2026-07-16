<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\TeamAdSpendGoal;
use App\Models\Workspace;
use App\Queries\TeamAdSpendGoalStatusQuery;
use App\Support\SalesMarketingDashboard;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamAdSpendGoalController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        // Rendered as the "Ad Spend Goals" tab of the S&M dashboard, so it
        // shares that dashboard's gating (module flag + permission).
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        // Team visibility: scoped users see only their team(s)' goals; the
        // "viewing as team" switcher narrows everyone to the chosen team. A null
        // scope means unrestricted; an empty array yields whereIn(..., []) → no
        // rows, failing closed.
        $teamIds = TeamVisibility::scopeTeamIds($request->user(), $workspace);

        $goals = TeamAdSpendGoal::where('workspace_id', $workspace->id)
            ->when($teamIds !== null, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->with(['team:id,name', 'milestones', 'members.user:id,name'])
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

        // Team picker for the create/edit form — the teams the user may set a
        // goal for (all for unrestricted, own teams for scoped users), each with
        // its members for the per-member allocation UI.
        $teams = TeamVisibility::selectableTeams($request->user(), $workspace)
            ->load('members:id,name');

        return Inertia::render('workspaces/ad-spend-goals/index', [
            'workspace' => $workspace,
            'goals' => $goals,
            'teams' => $teams,
            'canManage' => $request->user()->hasPermission(Permission::ManageAdSpendGoals->value, $workspace),
            'tabs' => SalesMarketingDashboard::tabs($workspace),
            'activeTab' => 'ad-spend-goals',
        ]);
    }

    public function show(Request $request, Workspace $workspace, TeamAdSpendGoal $goal)
    {
        $this->authorize(Permission::ViewAdSpendGoals->value, $workspace);

        $this->guardOwnership($workspace, $goal);

        $goal->load('team:id,name', 'milestones', 'members.user:id,name');

        $status = (new TeamAdSpendGoalStatusQuery($workspace))->statusFor($goal);

        // Team picker for the create/edit form — the teams the user may set a
        // goal for (all for unrestricted, own teams for scoped users), each with
        // its members for the per-member allocation UI.
        $teams = TeamVisibility::selectableTeams($request->user(), $workspace)
            ->load('members:id,name');

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
        $milestones = $validated['milestones'] ?? [];
        $members = $validated['members'] ?? [];
        unset($validated['milestones'], $validated['members']);

        $goal = TeamAdSpendGoal::create([
            'workspace_id' => $workspace->id,
            ...$validated,
        ]);

        $this->syncMilestones($goal, $milestones);
        $this->syncMembers($goal, $members);

        return redirect()->back()->with('success', 'Ad spend goal created successfully.');
    }

    public function update(Request $request, Workspace $workspace, TeamAdSpendGoal $goal)
    {
        $this->authorize(Permission::ManageAdSpendGoals->value, $workspace);

        $this->guardOwnership($workspace, $goal);

        $validated = $this->validateGoal($request, $workspace);
        $milestones = $validated['milestones'] ?? [];
        $members = $validated['members'] ?? [];
        unset($validated['milestones'], $validated['members']);

        $goal->update($validated);

        $this->syncMilestones($goal, $milestones);
        $this->syncMembers($goal, $members);

        return redirect()->back()->with('success', 'Ad spend goal updated successfully.');
    }

    /**
     * Replace the goal's milestones with the submitted set (small list, so a
     * delete-and-recreate keeps it simple).
     *
     * @param  array<int, array{amount: mixed, label?: string|null}>  $milestones
     */
    private function syncMilestones(TeamAdSpendGoal $goal, array $milestones): void
    {
        $goal->milestones()->delete();

        foreach ($milestones as $milestone) {
            $goal->milestones()->create([
                'amount' => $milestone['amount'],
                'label' => $milestone['label'] ?? null,
            ]);
        }
    }

    /**
     * Replace the goal's per-member target slices with the submitted set.
     *
     * @param  array<int, array{user_id: int, daily_target: mixed}>  $members
     */
    private function syncMembers(TeamAdSpendGoal $goal, array $members): void
    {
        $goal->members()->delete();

        foreach ($members as $member) {
            $goal->members()->create([
                'user_id' => $member['user_id'],
                'daily_target' => $member['daily_target'],
            ]);
        }
    }

    public function destroy(Request $request, Workspace $workspace, TeamAdSpendGoal $goal)
    {
        $this->authorize(Permission::ManageAdSpendGoals->value, $workspace);

        $this->guardOwnership($workspace, $goal);

        $goal->delete();

        // Redirect to the list (not back) so deleting from the detail page,
        // whose URL no longer resolves, doesn't 404. The list now lives on the
        // S&M dashboard's Ad Spend Goals tab.
        return redirect()
            ->route('workspaces.sales-marketing.dashboard.ad-spend-goals', $workspace)
            ->with('success', 'Ad spend goal deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGoal(Request $request, Workspace $workspace): array
    {
        $validated = $request->validate([
            'team_id' => [
                'required',
                Rule::exists('teams', 'id')->where('workspace_id', $workspace->id),
            ],
            'daily_target' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            // Optional stepping-stone thresholds, each below the daily target.
            'milestones' => ['array'],
            'milestones.*.amount' => ['required', 'numeric', 'gt:0', 'lt:daily_target'],
            'milestones.*.label' => ['nullable', 'string', 'max:255'],
            // Optional per-member target slices. Each member must belong to the
            // selected team; the slices must sum to at least the daily target.
            'members' => ['array'],
            'members.*.user_id' => [
                'required',
                'integer',
                Rule::exists('team_user', 'user_id')
                    ->where('team_id', $request->input('team_id')),
            ],
            'members.*.daily_target' => ['required', 'numeric', 'gte:0'],
        ]);

        $members = $validated['members'] ?? [];
        if (! empty($members)) {
            $sum = array_sum(array_map(fn ($m) => (float) $m['daily_target'], $members));
            $target = (float) $validated['daily_target'];

            // Allow a hair of float slack.
            if ($sum + 0.01 < $target) {
                throw ValidationException::withMessages([
                    'members' => 'Member targets must add up to at least the goal’s daily target of ₱'
                        .number_format($target, 2).' (currently ₱'.number_format($sum, 2).').',
                ]);
            }
        }

        return $validated;
    }

    private function guardOwnership(Workspace $workspace, TeamAdSpendGoal $goal): void
    {
        if ($goal->workspace_id !== $workspace->id) {
            abort(403, 'This goal does not belong to the current workspace.');
        }
    }
}
