<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\OptimizationRule;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class OptimizationRuleController extends Controller
{
    /**
     * Display the optimization rules page.
     */
    public function page(Workspace $workspace, Request $request)
    {
        $query = QueryBuilder::for(OptimizationRule::class)
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

        // Apply date range filter if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->get('start_date') . ' 00:00:00',
                $request->get('end_date') . ' 23:59:59'
            ]);
        }

        $rules = $query->paginate($request->get('perPage', 20))
            ->withQueryString();

        return Inertia::render('workspaces/ads-manager/optimization-rules/index', [
            'workspace' => $workspace,
            'rules' => $rules,
            'query' => [
                'sort' => $request->get('sort'),
                'perPage' => $request->get('perPage', 20),
                'page' => $request->get('page', 1),
                'filter' => [
                    'search' => $request->get('filter.search'),
                    'status' => $request->get('filter.status'),
                ],
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
            ],
        ]);
    }

    /**
     * Display a listing of the resource (API).
     */
    public function index(Workspace $workspace, Request $request)
    {
        $rules = QueryBuilder::for(OptimizationRule::class)
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
            ->defaultSort('-created_at')
            ->paginate($request->get('perPage', 20))
            ->withQueryString();

        return response()->json($rules);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target' => 'required|in:campaign,ad_set',
            'action' => 'required|in:increase_budget_fixed,decrease_budget_fixed,increase_budget_percentage,decrease_budget_percentage',
            'action_value' => 'nullable|numeric|min:0',
            'conditions' => 'required|array',
            'conditions.*.metric' => 'required|in:spend,impressions,clicks,sales,roas',
            'conditions.*.operator' => 'required|in:greater_than,less_than,equal,greater_than_or_equal,less_than_or_equal',
            'conditions.*.value' => 'required|numeric',
            'status' => 'nullable|in:active,paused',
        ]);

        $rule = OptimizationRule::create([
            ...$validated,
            'workspace_id' => $workspace->id,
            'status' => $validated['status'] ?? 'active',
        ]);

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

        return response()->json($optimizationRule);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Workspace $workspace, OptimizationRule $optimizationRule)
    {
        // Ensure the rule belongs to the workspace
        if ($optimizationRule->workspace_id !== $workspace->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'target' => 'sometimes|required|in:campaign,ad_set',
            'action' => 'sometimes|required|in:increase_budget_fixed,decrease_budget_fixed,increase_budget_percentage,decrease_budget_percentage',
            'action_value' => 'nullable|numeric|min:0',
            'conditions' => 'sometimes|required|array',
            'conditions.*.metric' => 'required|in:spend,impressions,clicks,sales,roas',
            'conditions.*.operator' => 'required|in:greater_than,less_than,equal,greater_than_or_equal,less_than_or_equal',
            'conditions.*.value' => 'required|numeric',
            'status' => 'nullable|in:active,paused',
        ]);

        $optimizationRule->update($validated);

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
