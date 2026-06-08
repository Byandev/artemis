<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\OptimizationProposal;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;

class OptimizationRuleController extends Controller
{
    /**
     * Metrics the evaluator can compute (computed ratios + raw insight columns).
     *
     * @see OptimizationRuleEvaluator
     */
    private const METRICS = [
        'roas', 'cpa', 'cpc', 'ctr', 'cpm', 'cost_per_lead', 'cost_per_messaging_conversation',
        'spend', 'impressions', 'clicks', 'purchases', 'purchase_value', 'leads', 'conversions',
        'messaging_conversations_started',
    ];

    private const OPERATORS = ['>', '<', '>=', '<=', '='];

    private const TIME_WINDOWS = ['today', 'yesterday', 'last_3_days', 'last_7_days', 'previous_3_days', 'previous_7_days'];

    private const ACTIONS = ['pause', 'enable', 'increase_budget', 'decrease_budget'];

    private const BUDGET_ACTIONS = ['increase_budget', 'decrease_budget'];

    public function index(Workspace $workspace): Response
    {
        $rules = OptimizationRule::where('workspace_id', $workspace->id)
            ->with(['conditions', 'adAccounts:id,name'])
            ->withCount('logs')
            ->latest()
            ->get();

        return Inertia::render('workspaces/integrations/meta-ads/optimization-rules/index', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'rules' => $rules,
        ]);
    }

    public function create(Workspace $workspace): Response
    {
        return Inertia::render('workspaces/integrations/meta-ads/optimization-rules/create', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'options' => $this->options($workspace),
        ]);
    }

    public function edit(Workspace $workspace, OptimizationRule $optimizationRule): Response
    {
        $this->authorizeRule($workspace, $optimizationRule);

        $optimizationRule->load(['conditions', 'adAccounts:id,name']);

        return Inertia::render('workspaces/integrations/meta-ads/optimization-rules/edit', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'rule' => $optimizationRule,
            // String ids so large Meta account ids stay precise in JSON.
            'selectedAdAccountIds' => $optimizationRule->adAccounts
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values(),
            'options' => $this->options($workspace),
        ]);
    }

    public function approvals(Workspace $workspace): Response
    {
        $proposals = OptimizationProposal::where('workspace_id', $workspace->id)
            ->where('status', 'pending')
            ->with(['rule:id,name,execution_mode', 'adAccount:id,name'])
            ->latest()
            ->get();

        return Inertia::render('workspaces/integrations/meta-ads/optimization-rules/approvals', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'proposals' => $proposals,
        ]);
    }

    public function approveProposal(Request $request, Workspace $workspace, OptimizationProposal $proposal): RedirectResponse
    {
        $this->reviewProposal($request, $workspace, $proposal, 'approved');

        return back()->with('success', 'Proposal approved.');
    }

    public function rejectProposal(Request $request, Workspace $workspace, OptimizationProposal $proposal): RedirectResponse
    {
        $this->reviewProposal($request, $workspace, $proposal, 'rejected');

        return back()->with('success', 'Proposal rejected.');
    }

    private function reviewProposal(Request $request, Workspace $workspace, OptimizationProposal $proposal, string $status): void
    {
        abort_unless($proposal->workspace_id === $workspace->id, 404);
        abort_unless($proposal->status === 'pending', 422, 'This proposal has already been reviewed.');

        $proposal->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $this->validateRule($request, $workspace);

        $rule = OptimizationRule::create($this->ruleAttributes($data, $workspace));
        $rule->adAccounts()->sync($data['meta_ads_account_ids']);
        $this->replaceConditions($rule, $data['conditions']);

        return redirect()
            ->route('workspaces.metaads.optimization-rules.index', $workspace->slug)
            ->with('success', 'Optimization rule created.');
    }

    public function update(Request $request, Workspace $workspace, OptimizationRule $optimizationRule): RedirectResponse
    {
        $this->authorizeRule($workspace, $optimizationRule);

        $data = $this->validateRule($request, $workspace);

        $optimizationRule->update($this->ruleAttributes($data, $workspace));
        $optimizationRule->adAccounts()->sync($data['meta_ads_account_ids']);
        $this->replaceConditions($optimizationRule, $data['conditions']);

        return redirect()
            ->route('workspaces.metaads.optimization-rules.index', $workspace->slug)
            ->with('success', 'Optimization rule updated.');
    }

    public function toggle(Workspace $workspace, OptimizationRule $optimizationRule): RedirectResponse
    {
        $this->authorizeRule($workspace, $optimizationRule);

        $optimizationRule->update(['is_active' => ! $optimizationRule->is_active]);

        return back();
    }

    public function destroy(Workspace $workspace, OptimizationRule $optimizationRule): RedirectResponse
    {
        $this->authorizeRule($workspace, $optimizationRule);

        // Conditions cascade via the foreign key; logs are retained as history.
        $optimizationRule->delete();

        return back()->with('success', 'Optimization rule deleted.');
    }

    private function authorizeRule(Workspace $workspace, OptimizationRule $rule): void
    {
        abort_unless($rule->workspace_id === $workspace->id, 404);
    }

    /**
     * Option lists shared by the create and edit forms.
     *
     * @return array<string, mixed>
     */
    private function options(Workspace $workspace): array
    {
        return [
            'adAccounts' => AdAccount::forWorkspace($workspace)
                ->where('active_sync', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (AdAccount $account) => [
                    'id' => (string) $account->id,
                    'name' => $account->name,
                ])
                ->values(),
            'metrics' => self::METRICS,
            'operators' => self::OPERATORS,
            'timeWindows' => self::TIME_WINDOWS,
            'actions' => self::ACTIONS,
            'targetTypes' => ['campaign', 'ad_set'],
            'conditionOperators' => ['and', 'or'],
            'adjustmentTypes' => ['percentage', 'fixed'],
            'executionModes' => ['approval', 'automatic'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request, Workspace $workspace): array
    {
        $isBudgetAction = fn () => in_array($request->input('action'), self::BUDGET_ACTIONS, true);

        $workspaceAccountIds = AdAccount::forWorkspace($workspace)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'meta_ads_account_ids' => ['required', 'array', 'min:1'],
            'meta_ads_account_ids.*' => [Rule::in($workspaceAccountIds)],
            'target_type' => ['required', Rule::in(['campaign', 'ad_set'])],
            'condition_operator' => ['required', Rule::in(['and', 'or'])],
            'action' => ['required', Rule::in(self::ACTIONS)],
            'adjustment_type' => [Rule::requiredIf($isBudgetAction), 'nullable', Rule::in(['percentage', 'fixed'])],
            'adjustment_value' => [Rule::requiredIf($isBudgetAction), 'nullable', 'numeric', 'min:0'],
            'max_adjustment_amount' => ['nullable', 'numeric', 'min:0'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'is_active' => ['boolean'],
            'execution_mode' => ['required', Rule::in(['automatic', 'approval'])],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.metric' => ['required', Rule::in(self::METRICS)],
            'conditions.*.operator' => ['required', Rule::in(self::OPERATORS)],
            'conditions.*.value' => ['required', 'numeric'],
            'conditions.*.time_window' => ['required', Rule::in(self::TIME_WINDOWS)],
        ]);
    }

    /**
     * Build the persistable rule attributes, nulling budget fields for
     * non-budget actions so stale adjustment config can't linger.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ruleAttributes(array $data, Workspace $workspace): array
    {
        $isBudgetAction = in_array($data['action'], self::BUDGET_ACTIONS, true);

        return [
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'target_type' => $data['target_type'],
            'condition_operator' => $data['condition_operator'],
            'action' => $data['action'],
            'adjustment_type' => $isBudgetAction ? $data['adjustment_type'] : null,
            'adjustment_value' => $isBudgetAction ? $data['adjustment_value'] : null,
            // Only a percentage adjustment can be capped.
            'max_adjustment_amount' => $isBudgetAction && ($data['adjustment_type'] ?? null) === 'percentage'
                ? ($data['max_adjustment_amount'] ?? null)
                : null,
            'budget_min' => $isBudgetAction ? ($data['budget_min'] ?? null) : null,
            'budget_max' => $isBudgetAction ? ($data['budget_max'] ?? null) : null,
            'is_active' => $data['is_active'] ?? true,
            'execution_mode' => $data['execution_mode'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     */
    private function replaceConditions(OptimizationRule $rule, array $conditions): void
    {
        $rule->conditions()->delete();

        foreach ($conditions as $condition) {
            $rule->conditions()->create([
                'metric' => $condition['metric'],
                'operator' => $condition['operator'],
                'value' => $condition['value'],
                'time_window' => $condition['time_window'],
            ]);
        }
    }
}
