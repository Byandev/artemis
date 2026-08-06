<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\SalesTarget;
use App\Models\Workspace;
use App\Support\SalesMarketingDashboard;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The "Sales Targets" tab of the S&M dashboard: dated target sets, each holding
 * one amount per team.
 */
class SalesTargetController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $targets = SalesTarget::ofWorkspace($workspace)
            ->with(['teamTargets.team:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        // Teams the user may set an amount for — all of them when unrestricted,
        // their own when scoped.
        $teams = TeamVisibility::selectableTeams($request->user(), $workspace)
            ->map(fn ($team) => $team->only(['id', 'name']))
            ->values();

        return Inertia::render('workspaces/sales-targets/index', [
            'workspace' => $workspace,
            'targets' => $targets,
            'teams' => $teams,
            'canManage' => $request->user()->hasPermission(Permission::EditTeams->value, $workspace),
            'tabs' => SalesMarketingDashboard::tabs($workspace),
            'activeTab' => 'sales-targets',
        ]);
    }

    public function show(Request $request, Workspace $workspace, SalesTarget $salesTarget)
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewSalesMarketingDashboard->value, $workspace);

        $this->guardOwnership($workspace, $salesTarget);

        $salesTarget->load('teamTargets.team:id,name');

        return Inertia::render('workspaces/sales-targets/show', [
            'workspace' => $workspace,
            'target' => $salesTarget,
            'teams' => TeamVisibility::selectableTeams($request->user(), $workspace)
                ->map(fn ($team) => $team->only(['id', 'name']))
                ->values(),
            'canManage' => $request->user()->hasPermission(Permission::EditTeams->value, $workspace),
            'tabs' => SalesMarketingDashboard::tabs($workspace),
            'activeTab' => 'sales-targets',
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::EditTeams->value, $workspace);

        $validated = $this->validatePayload($request, $workspace);

        DB::transaction(function () use ($workspace, $validated) {
            $target = SalesTarget::create([
                'workspace_id' => $workspace->id,
                'date' => $validated['date'],
                'name' => $validated['name'],
            ]);

            $target->teamTargets()->createMany($validated['teams']);
        });

        return redirect()->back()->with('success', 'Sales target created.');
    }

    public function update(Request $request, Workspace $workspace, SalesTarget $salesTarget)
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::EditTeams->value, $workspace);

        $this->guardOwnership($workspace, $salesTarget);

        // Ignore itself, so saving without moving the date isn't a clash.
        $validated = $this->validatePayload($request, $workspace, $salesTarget);

        DB::transaction(function () use ($salesTarget, $validated) {
            $salesTarget->update([
                'date' => $validated['date'],
                'name' => $validated['name'],
            ]);

            // Replace the team rows wholesale — the form always submits the full
            // set, so removals are just absences from the payload.
            $salesTarget->teamTargets()->delete();
            $salesTarget->teamTargets()->createMany($validated['teams']);
        });

        return redirect()->back()->with('success', 'Sales target updated.');
    }

    public function destroy(Request $request, Workspace $workspace, SalesTarget $salesTarget)
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::EditTeams->value, $workspace);

        $this->guardOwnership($workspace, $salesTarget);

        // Team rows go with it via the FK's cascade.
        $salesTarget->delete();

        return redirect()->back()->with('success', 'Sales target deleted.');
    }

    /**
     * Shared validation for store and update.
     *
     * @return array{date: string, name: string, teams: array<int, array{team_id: int, sales_target: string}>}
     */
    private function validatePayload(Request $request, Workspace $workspace, ?SalesTarget $ignore = null): array
    {
        $validated = $request->validate([
            'date' => [
                'required',
                'date',
                // One target per date. Backed by a unique index; validated here
                // so the user gets a message on the field instead of a 500.
                Rule::unique('sales_targets', 'date')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id))
                    ->ignore($ignore?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'teams' => ['required', 'array', 'min:1'],
            'teams.*.team_id' => ['required', 'integer', 'distinct'],
            'teams.*.sales_target' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ], [
            'date.unique' => 'A sales target already exists for this date.',
            'teams.required' => 'Add at least one team to the target.',
            'teams.*.team_id.distinct' => 'Each team can only appear once.',
        ]);

        // Only teams of this workspace, and only ones the user can see — a
        // scoped user must not be able to set another team's number by id.
        $allowedTeamIds = TeamVisibility::selectableTeams($request->user(), $workspace)
            ->pluck('id')
            ->all();

        foreach ($validated['teams'] as $i => $team) {
            if (! in_array((int) $team['team_id'], $allowedTeamIds, true)) {
                abort(403, 'You cannot set a target for one of the selected teams.');
            }

            $validated['teams'][$i] = [
                'team_id' => (int) $team['team_id'],
                'sales_target' => $team['sales_target'],
            ];
        }

        return $validated;
    }

    private function guardOwnership(Workspace $workspace, SalesTarget $salesTarget): void
    {
        if ($salesTarget->workspace_id !== $workspace->id) {
            abort(403, 'This sales target does not belong to the current workspace.');
        }
    }
}
