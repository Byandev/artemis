<?php

namespace App\Http\Controllers\Workspaces\AdsManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreOptimizationRuleRequest;
use App\Http\Requests\Workspaces\UpdateOptimizationRuleRequest;
use App\Models\OptimizationRule;
use App\Models\OptimizationRuleCondition;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OptimizationRuleController extends Controller
{
    /**
     * Build the query for optimization rules.
     */
    private function buildQuery(Workspace $workspace, Request $request)
    {
        return QueryBuilder::for(OptimizationRule::class)
            ->where('workspace_id', $workspace->id)
            ->allowedFilters([
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts([
                'name',
                'description',
                'target',
                'action',
                'status',
                'created_at',
                'updated_at',
            ])
            ->defaultSort('-created_at');
    }

    /**
     * Display the optimization rules page.
     */
    public function page(Workspace $workspace, Request $request)
    {
        $rules = $this->buildQuery($workspace, $request)
            ->with('conditions')
            ->paginate($request->get('perPage', 10))
            ->withQueryString();

        return Inertia::render('workspaces/ads-manager/optimization-rules', [
            'workspace' => $workspace,
            'rules' => $rules,
            'query' => [
                'sort' => $request->get('sort'),
                'perPage' => $request->get('perPage', 10),
                'page' => $request->get('page', 1),
                'filter' => [
                    'search' => $request->get('filter.search'),
                    'status' => $request->get('filter.status'),
                ],
            ],
        ]);
    }

    /**
     * Show the create page.
     */
    public function create(Workspace $workspace)
    {
        return Inertia::render('workspaces/ads-manager/optimization-rules-form', [
            'workspace' => $workspace,
            'rule' => null,
        ]);
    }

    /**
     * Show the edit page.
     */
    public function edit(Workspace $workspace, OptimizationRule $optimizationRule)
    {
        // Ensure the rule belongs to the workspace
        if ($optimizationRule->workspace_id !== $workspace->id) {
            abort(403);
        }

        $optimizationRule->load('conditions');

        return Inertia::render('workspaces/ads-manager/optimization-rules-form', [
            'workspace' => $workspace,
            'rule' => $optimizationRule,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOptimizationRuleRequest $request, Workspace $workspace)
    {
        $validated = $request->validated();
        $conditions = $validated['conditions'] ?? [];

        // Remove conditions from the main create array
        unset($validated['conditions']);

        $rule = OptimizationRule::create([
            ...$validated,
            'workspace_id' => $workspace->id,
            'status' => $validated['status'] ?? 'active',
        ]);

        // Create conditions
        foreach ($conditions as $condition) {
            OptimizationRuleCondition::create([
                'optimization_rule_id' => $rule->id,
                'metric' => $condition['metric'],
                'operator' => $condition['operator'],
                'value' => $condition['value'],
            ]);
        }

        // Reload with conditions
        $rule->load('conditions');

        return response()->json($rule, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace, OptimizationRule $optimizationRule)
    {
        // Ensure the rule belongs to the workspace
        if ($optimizationRule->workspace_id !== $workspace->id) {
            abort(403);
        }

        $optimizationRule->load('conditions');

        return response()->json($optimizationRule);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOptimizationRuleRequest $request, Workspace $workspace, OptimizationRule $optimizationRule)
    {
        // Ensure the rule belongs to the workspace
        if ($optimizationRule->workspace_id !== $workspace->id) {
            abort(403);
        }

        $validated = $request->validated();
        $conditions = $validated['conditions'] ?? null;

        // Remove conditions from the update array
        unset($validated['conditions']);

        $optimizationRule->update($validated);

        // Sync conditions if provided
        if ($conditions !== null) {
            // Delete existing conditions
            $optimizationRule->conditions()->delete();

            // Create new conditions
            foreach ($conditions as $condition) {
                OptimizationRuleCondition::create([
                    'optimization_rule_id' => $optimizationRule->id,
                    'metric' => $condition['metric'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'],
                ]);
            }
        }

        // Reload with conditions
        $optimizationRule->load('conditions');

        return response()->json($optimizationRule);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workspace $workspace, OptimizationRule $optimizationRule)
    {
        // Ensure the rule belongs to the workspace
        if ($optimizationRule->workspace_id !== $workspace->id) {
            abort(403);
        }

        $optimizationRule->delete();

        return response()->json(null, 204);
    }
}
