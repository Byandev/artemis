<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\OptimizationRule;
use App\Models\Workspace;
use Illuminate\Http\Request;

class OptimizationRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Workspace $workspace, Request $request)
    {
        $query = OptimizationRule::where('workspace_id', $workspace->id);

        // Handle sorting
        if ($request->has('sort')) {
            $sortFields = explode(',', $request->sort);
            
            foreach ($sortFields as $sortField) {
                $direction = 'asc';
                $field = $sortField;
                
                // Check if descending (prefixed with -)
                if (str_starts_with($sortField, '-')) {
                    $direction = 'desc';
                    $field = substr($sortField, 1);
                }
                
                // Validate and apply sorting
                $allowedSorts = ['name', 'description', 'target', 'action', 'status', 'created_at', 'updated_at'];
                if (in_array($field, $allowedSorts)) {
                    $query->orderBy($field, $direction);
                }
            }
        } else {
            // Default sorting
            $query->orderBy('created_at', 'desc');
        }

        $rules = $query->paginate(20);

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
