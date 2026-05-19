<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use App\Support\Metrics\MetricRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AdminWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('admin/workspaces/index', [
            'workspaces' => Workspace::query()
                // LOAD RELATIONSHIP HERE: so we see the currently allowed metrics
                ->with(['owner:id,name', 'subscription.plan', 'metricSetting'])
                ->withCount('pages')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                })
                ->orderBy(
                    in_array($request->sort, ['name', 'slug', 'created_at', 'pages_count']) ? $request->sort : 'created_at',
                    $request->direction === 'asc' ? 'asc' : 'desc'
                )
                ->paginate((int) $request->input('per_page', 15))
                ->withQueryString(),

            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),

            // KEEP THIS: This provides the labels and groups for the checkboxes in the modal
            'metricConfigs' => MetricRegistry::configs(),

            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    /**
     * NOTE: updateMetrics was removed from here because you are now using 
     * MetricSettingController@update to handle the WorkspaceMetricSetting model.
     */

    public function updateSubscription(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'status' => 'required|in:trialing,active,past_due,canceled,expired',
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
        $subscription = $workspace->subscription;
        $now = Carbon::now();

        if ($subscription) {
            $data = [
                'subscription_plan_id' => $validated['subscription_plan_id'],
                'status' => $validated['status'],
            ];

            if ($validated['status'] === 'trialing') {
                $data['trial_ends_at'] = $now->copy()->addDays($plan->trial_days ?? 30);
                $data['current_period_start'] = $now;
                $data['current_period_end'] = $now->copy()->addDays($plan->trial_days ?? 30);
            } elseif ($validated['status'] === 'active') {
                $data['trial_ends_at'] = null;
                $data['current_period_start'] = $now;
                $data['current_period_end'] = $now->copy()->addMonth();
            }

            $subscription->update($data);
        } else {
            Subscription::create([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $validated['subscription_plan_id'],
                'status' => $validated['status'],
                'trial_ends_at' => $validated['status'] === 'trialing' ? $now->copy()->addDays($plan->trial_days ?? 30) : null,
                'current_period_start' => $now,
                'current_period_end' => $validated['status'] === 'trialing'
                    ? $now->copy()->addDays($plan->trial_days ?? 30)
                    : $now->copy()->addMonth(),
            ]);
        }

        return back()->with('success', "Subscription updated for {$workspace->name}.");
    }

    public function updateModules(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'inventory_module_enabled' => ['required', 'boolean'],
            'finance_module_enabled' => ['required', 'boolean'],
            'products_module_enabled' => ['required', 'boolean'],
            'teams_module_enabled' => ['required', 'boolean'],
            'checklist_module_enabled' => ['required', 'boolean'],
            'csr_module_enabled' => ['required', 'boolean'],
            'rmo_module_enabled' => ['required', 'boolean'],
            'leaderboard_module_enabled' => ['required', 'boolean'],
            'botcake_module_enabled' => ['sometimes', 'boolean'],
        ]);

        $workspace->update($validated);

        return back()->with('success', "Modules updated for {$workspace->name}.");
    }
}
