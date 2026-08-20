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

    /**
     * The window a target's date may fall in, mirrored by the form dialog.
     *
     * The form picks its date from a calendar rather than a text box, but the
     * picker's header year is still a typable number field, and a year the
     * `date` column cannot hold used to get past validation and fail at the
     * insert instead of coming back as a message on the field.
     */
    private const MIN_DATE = '2000-01-01';

    private const MAX_DATE = '2100-12-31';

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
                'target_roas' => $validated['target_roas'],
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
                'target_roas' => $validated['target_roas'],
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

        // Where the delete was fired from, while the record still resolves.
        $cameFromItsOwnPage = parse_url(url()->previous(), PHP_URL_PATH) === parse_url(
            route('workspaces.sales-marketing.dashboard.sales-targets.show', [$workspace, $salesTarget]),
            PHP_URL_PATH
        );

        // Team rows go with it via the FK's cascade.
        $salesTarget->delete();

        // A delete fired from the target's own detail page can't go `back()` —
        // back is the record that was just deleted, so it would land on a 404.
        // From the list, `back()` is right: it keeps the page and query string.
        return ($cameFromItsOwnPage
            ? redirect()->route('workspaces.sales-marketing.dashboard.sales-targets', $workspace)
            : redirect()->back()
        )->with('success', 'Sales target deleted.');
    }

    /**
     * Shared validation for store and update.
     *
     * A selected team may carry a sales amount, an ad budget, or both — the
     * selection itself is what puts it on the target, so neither number is
     * required.
     *
     * @return array{date: string, name: string, target_roas: string|null, teams: array<int, array{team_id: int, sales_target: string|null, ad_budget: string|null}>}
     */
    private function validatePayload(Request $request, Workspace $workspace, ?SalesTarget $ignore = null): array
    {
        $validated = $request->validate([
            'date' => [
                // One message at a time — a date that isn't a date has nothing
                // useful to say about its range or its uniqueness.
                'bail',
                'required',
                // The form posts ISO, so anything else is malformed. Stricter
                // than `date`, which takes "next tuesday" and rolls a day the
                // calendar doesn't have — Feb 30 was stored as Mar 2.
                'date_format:Y-m-d',
                'after_or_equal:'.self::MIN_DATE,
                'before_or_equal:'.self::MAX_DATE,
                // One target per date. Backed by a unique index; validated here
                // so the user gets a message on the field instead of a 500.
                Rule::unique('sales_targets', 'date')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id))
                    ->ignore($ignore?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'target_roas' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'teams' => ['required', 'array', 'min:1'],
            'teams.*.team_id' => ['required', 'integer', 'distinct'],
            'teams.*.sales_target' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'teams.*.ad_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ], [
            'date.required' => 'Pick a date for the target.',
            'date.date_format' => 'Enter a real calendar date as YYYY-MM-DD.',
            'date.after_or_equal' => 'Pick a date between 2000 and 2100.',
            'date.before_or_equal' => 'Pick a date between 2000 and 2100.',
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
                // An empty box means "not set", which is not the same as zero.
                'sales_target' => $this->amount($team['sales_target'] ?? null),
                'ad_budget' => $this->amount($team['ad_budget'] ?? null),
            ];
        }

        $validated['target_roas'] = $this->amount($validated['target_roas'] ?? null);

        return $validated;
    }

    /** Normalises a submitted money/ratio field: blank becomes null. */
    private function amount(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function guardOwnership(Workspace $workspace, SalesTarget $salesTarget): void
    {
        if ($salesTarget->workspace_id !== $workspace->id) {
            abort(403, 'This sales target does not belong to the current workspace.');
        }
    }
}
