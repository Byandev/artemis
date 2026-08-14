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
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AdminWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = Workspace::query()
            ->select('workspaces.*')
            ->with(['owner:id,name', 'subscription.plan', 'metricSetting'])
            ->withCount([
                'pages',
                'shops',

                // A page can send SMS parcel updates only when both InfoTxt
                // credentials are present.
                'pages as sms_parcel_journey_pages_count' => function ($query) {
                    $query->whereNotNull('infotxt_token')
                        ->where('infotxt_token', '!=', '')
                        ->whereNotNull('infotxt_user_id')
                        ->where('infotxt_user_id', '!=', '');
                },

                // ...and chat parcel updates only when both Botcake flow and
                // custom field are mapped.
                'pages as chat_parcel_journey_pages_count' => function ($query) {
                    $query->whereNotNull('parcel_journey_flow_id')
                        ->whereNotNull('parcel_journey_custom_field_id');
                },
            ]);

        $workspaces = QueryBuilder::for($baseQuery)
            ->allowedFilters([
            ])
            ->allowedSorts([
                'name',
                'slug',
                'created_at',
                'pages_count',
                'shops_count',
                'sms_parcel_journey_pages_count',
                'chat_parcel_journey_pages_count',

                AllowedSort::callback('owner', function ($query, bool $descending) {
                    $direction = $descending ? 'desc' : 'asc';
                    $query->leftJoin('users', 'workspaces.owner_id', '=', 'users.id')
                        ->orderBy('users.name', $direction)
                        ->orderBy('workspaces.name');
                }),

                AllowedSort::callback('subscription', function ($query, bool $descending) {
                    $direction = $descending ? 'desc' : 'asc';
                    $query->leftJoin('subscriptions', 'workspaces.id', '=', 'subscriptions.workspace_id')
                        ->leftJoin('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                        ->orderBy('subscription_plans.name', $direction)
                        ->orderBy('workspaces.name');
                }),
            ]);

        if (! $request->has('sort')) {
            $workspaces->orderBy('workspaces.created_at', 'desc');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $workspaces->where(function ($query) use ($search) {
                $query->where('workspaces.name', 'like', "%{$search}%")
                    ->orWhere('workspaces.slug', 'like', "%{$search}%");
            });
        }

        return Inertia::render('admin/workspaces/index', [
            'workspaces' => $workspaces
                ->paginate((int) $request->input('per_page', 15))
                ->withQueryString(),

            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),
            'metricConfigs' => MetricRegistry::configs(),
            'filters' => $request->only(['search', 'sort']),
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
            'current_period_start' => 'nullable|date',
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
        $subscription = $workspace->subscription;

        // Every other date is derived from this anchor. It defaults to now, but
        // an admin can back- or forward-date it so date-driven behaviour (trial
        // expiry, renewal, invoicing) can be exercised without waiting for the
        // calendar to catch up.
        $movedStart = filled($validated['current_period_start'] ?? null);
        $start = $movedStart
            ? Carbon::parse($validated['current_period_start'])->startOfDay()
            : Carbon::now();

        $trialDays = $plan->trial_days ?? 30;

        if ($subscription) {
            $data = [
                'subscription_plan_id' => $validated['subscription_plan_id'],
                'status' => $validated['status'],
            ];

            if ($validated['status'] === 'trialing') {
                $data['trial_ends_at'] = $start->copy()->addDays($trialDays);
                $data['current_period_start'] = $start;
                $data['current_period_end'] = $start->copy()->addDays($trialDays);
            } elseif ($validated['status'] === 'active') {
                $data['trial_ends_at'] = null;
                $data['current_period_start'] = $start;
                $data['current_period_end'] = $start->copy()->addMonth();
            } elseif ($movedStart) {
                // past_due / canceled / expired keep whatever dates they already
                // had, unless the admin explicitly moved the start.
                $data['current_period_start'] = $start;
                $data['current_period_end'] = $start->copy()->addMonth();
            }

            $subscription->update($data);
        } else {
            Subscription::create([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $validated['subscription_plan_id'],
                'status' => $validated['status'],
                'trial_ends_at' => $validated['status'] === 'trialing' ? $start->copy()->addDays($trialDays) : null,
                'current_period_start' => $start,
                'current_period_end' => $validated['status'] === 'trialing'
                    ? $start->copy()->addDays($trialDays)
                    : $start->copy()->addMonth(),
            ]);
        }

        return back()->with('success', "Subscription updated for {$workspace->name}.");
    }

    public function updateModules(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'inventory_module_enabled' => 'required|boolean',
            'finance_module_enabled' => 'required|boolean',
            'products_module_enabled' => 'required|boolean',
            'teams_module_enabled' => 'required|boolean',
            'checklist_module_enabled' => 'required|boolean',
            'csr_module_enabled' => 'required|boolean',
            'rmo_module_enabled' => 'required|boolean',
            'leaderboard_module_enabled' => 'required|boolean',
            'botcake_module_enabled' => 'required|boolean',
            'creatives_module_enabled' => 'required|boolean',
            'meta_ads_module_enabled' => 'required|boolean',
            'gencys_module_enabled' => 'required|boolean',
            'is_gencys_partner' => 'required|boolean',
            'sales_marketing_dashboard_module_enabled' => 'required|boolean',
            'video_editor_dashboard_module_enabled' => 'required|boolean',
            'csr_dashboard_module_enabled' => 'required|boolean',
            'sim_gateway_module_enabled' => 'required|boolean',
            'ad_spend_goals_module_enabled' => 'required|boolean',
        ]);

        $workspace->update($validated);

        return back()->with('success', "Modules updated for {$workspace->name}.");
    }

    public function updateMaxShops(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'max_shops' => 'nullable|integer|min:1',
        ]);

        $workspace->update($validated);

        return back()->with('success', "Max shops updated for {$workspace->name}.");
    }
}
