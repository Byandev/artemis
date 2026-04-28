<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSubscriptionPlanController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('admin/subscription-plans/index', [
            'plans' => SubscriptionPlan::query()
                ->withCount('subscriptions')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                ->orderBy(
                    in_array($request->sort, ['name', 'price_php', 'sort_order', 'is_active', 'subscriptions_count']) ? $request->sort : 'sort_order',
                    $request->direction === 'desc' ? 'desc' : 'asc'
                )
                ->paginate((int) $request->input('per_page', 15))
                ->withQueryString(),

            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/subscription-plans/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:subscription_plans,code',
            'name' => 'required|string|max:255',
            'price_php' => 'required|numeric|min:0',
            'order_limit' => 'nullable|integer|min:0',
            'page_limit' => 'nullable|integer|min:0',
            'data_retention_months' => 'required|integer|min:1',
            'analytics_tier' => 'required|in:basic,full',
            'parcel_journey_rate_php' => 'nullable|numeric|min:0',
            'parcel_journey_sms_enabled' => 'boolean',
            'support_tier' => 'required|in:chat,priority_chat,dedicated',
            'trial_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        SubscriptionPlan::create($validated);

        return redirect()->route('admin.subscription-plans.index')
            ->with('success', 'Subscription plan created.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        return Inertia::render('admin/subscription-plans/edit', [
            'plan' => $subscriptionPlan,
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:subscription_plans,code,' . $subscriptionPlan->id,
            'name' => 'required|string|max:255',
            'price_php' => 'required|numeric|min:0',
            'order_limit' => 'nullable|integer|min:0',
            'page_limit' => 'nullable|integer|min:0',
            'data_retention_months' => 'required|integer|min:1',
            'analytics_tier' => 'required|in:basic,full',
            'parcel_journey_rate_php' => 'nullable|numeric|min:0',
            'parcel_journey_sms_enabled' => 'boolean',
            'support_tier' => 'required|in:chat,priority_chat,dedicated',
            'trial_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $subscriptionPlan->update($validated);

        return redirect()->route('admin.subscription-plans.index')
            ->with('success', 'Subscription plan updated.');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        if ($subscriptionPlan->subscriptions()->exists()) {
            return back()->with('error', 'Cannot delete a plan with active subscriptions.');
        }

        $subscriptionPlan->delete();

        return redirect()->route('admin.subscription-plans.index')
            ->with('success', 'Subscription plan deleted.');
    }
}