<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AdminWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('admin/workspaces/index', [
            'workspaces' => Workspace::query()
                ->with(['owner:id,name', 'subscription.plan'])
                ->withCount('pages')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                })
                ->orderBy($request->sort ?? 'created_at', $request->direction ?? 'desc')
                ->paginate(15)
                ->withQueryString(),

            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),

            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

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
                $data['trial_ends_at'] = $now->copy()->addDays($plan->trial_days ?? 14);
                $data['current_period_start'] = $now;
                $data['current_period_end'] = $now->copy()->addDays($plan->trial_days ?? 14);
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
                'trial_ends_at' => $validated['status'] === 'trialing' ? $now->copy()->addDays($plan->trial_days ?? 14) : null,
                'current_period_start' => $now,
                'current_period_end' => $validated['status'] === 'trialing'
                    ? $now->copy()->addDays($plan->trial_days ?? 14)
                    : $now->copy()->addMonth(),
            ]);
        }

        return back()->with('success', "Subscription updated for {$workspace->name}.");
    }
}
