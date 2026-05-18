<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use App\Services\PostHogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class WorkspaceSetupController extends Controller
{
    /**
     * Show the workspace setup form (after registration).
     */
    public function create(Request $request)
    {
        // Check if user already has a workspace
        if ($request->user()->workspaces()->exists()) {
            $workspace = $request->user()->ownedWorkspaces()->first()
                ?? $request->user()->workspaces()->first();

            return redirect()->route('workspace.dashboard', $workspace->slug);
        }

        return Inertia::render('workspaces/setup', [
            'userName' => $request->user()->name,
        ]);
    }

    /**
     * Create the initial workspace for a new user.
     */
    public function store(Request $request)
    {
        // Check if user already has a workspace
        if ($request->user()->workspaces()->exists()) {
            $workspace = $request->user()->ownedWorkspaces()->first()
                ?? $request->user()->workspaces()->first();

            return redirect()->route('workspace.dashboard', $workspace->slug)
                ->with('info', 'You already have a workspace.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'min:3'],
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_order_volume' => ['required', 'string', 'in:below-500,500-1000,1000-5000,5000-10000,above-10000'],
        ]);

        // Create the workspace
        $workspace = Workspace::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'monthly_order_volume' => $validated['monthly_order_volume'] ?? null,
            'owner_id' => $request->user()->id,
        ]);

        // Add the user as owner in the pivot table
        $workspace->users()->attach($request->user()->id, ['role' => 'owner']);

        // Start free trial subscription
        $freeTrial = SubscriptionPlan::where('code', SubscriptionPlan::CODE_FREE_TRIAL)->first();
        if ($freeTrial) {
            $now = Carbon::now();
            Subscription::create([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $freeTrial->id,
                'status' => Subscription::STATUS_TRIALING,
                'trial_ends_at' => $now->copy()->addDays($freeTrial->trial_days ?? 30),
                'current_period_start' => $now,
                'current_period_end' => $now->copy()->addDays($freeTrial->trial_days ?? 30),
            ]);
        }

        // Set as current workspace
        session(['current_workspace_id' => $workspace->id]);

        (new PostHogService)->capture((string) $request->user()->id, 'workspace_created', [
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
            'monthly_order_volume' => $workspace->monthly_order_volume,
        ]);

        return redirect()->route('workspace.onboarding', $workspace->slug)
            ->with('success', 'Welcome! Let\'s connect your first page.');
    }
}
