<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use App\Services\PostHogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Pancake\Jobs\FetchShopOrders;
use Modules\Pancake\Jobs\FetchShopUsers;

class OnboardingController extends Controller
{
    public function create(Request $request, Workspace $workspace)
    {
        // Skip if workspace already has shops (onboarding already done)
        if ($workspace->shops()->exists()) {
            return redirect()->route('workspace.dashboard', $workspace->slug);
        }

        $info = $workspace->shopLimitInfo();

        return Inertia::render('workspaces/onboarding', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'shopLimit' => $info['limit'],
            'shopCount' => $info['count'],
            'shopLimitReached' => $info['reached'],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $info = $workspace->shopLimitInfo();
        if ($info['reached']) {
            throw ValidationException::withMessages([
                'shop_limit' => "You've reached your plan's shop limit ({$info['limit']}). Upgrade your plan to add more shops.",
            ]);
        }

        $validated = $request->validate([
            'shop_id' => ['required', 'integer'],
            'pos_token' => ['required', 'string', 'max:255'],
        ]);

        // Validate token against Pancake API
        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$validated['shop_id'], [
            'api_key' => $validated['pos_token'],
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages(['pos_token' => 'Invalid API Key. Please check your POS token.']);
        }

        $resJson = $response->json();

        // Create or get Shop
        $shop = Shop::firstOrCreate([
            'id' => $validated['shop_id'],
            'workspace_id' => $workspace->id,
        ], [
            'name' => $resJson['shop']['name'] ?? 'Shop '.$validated['shop_id'],
            'avatar_url' => $resJson['shop']['avatar_url'] ?? null,
            'pos_token' => $validated['pos_token'],
        ]);

        // Ensure an existing shop also has its token set.
        if (! $shop->pos_token) {
            $shop->update(['pos_token' => $validated['pos_token']]);
        }

        // Auto-create the shop's pages from the POS API response. Orders sync at
        // the shop level (FetchShopOrders, dispatched below).
        $createdPages = 0;
        foreach (collect($resJson['shop']['pages'] ?? []) as $pageData) {
            if (! isset($pageData['id'])) {
                continue;
            }

            $existing = Page::withTrashed()->find($pageData['id']);
            if ($existing && $existing->workspace_id !== $workspace->id) {
                continue;
            }

            Page::updateOrCreate(
                ['id' => $pageData['id']],
                [
                    'workspace_id' => $workspace->id,
                    'shop_id' => $shop->id,
                    'owner_id' => $request->user()->id,
                    'name' => $pageData['name'] ?? 'Page '.$pageData['id'],
                    'status' => 'active',
                ]
            );

            $createdPages++;
        }

        // Create free trial subscription if none exists
        if (! $workspace->subscription) {
            $trialPlan = SubscriptionPlan::where('code', SubscriptionPlan::CODE_FREE_TRIAL)->first();
            $trialDays = $trialPlan?->trial_days ?? 30;

            Subscription::create([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $trialPlan?->id,
                'status' => Subscription::STATUS_TRIALING,
                'trial_ends_at' => Carbon::now()->addDays($trialDays),
                'current_period_start' => Carbon::now(),
                'current_period_end' => Carbon::now()->addDays($trialDays),
            ]);
        }

        if ($shop->wasRecentlyCreated) {
            dispatch(new FetchShopUsers($shop))->onQueue('pancake');
            dispatch(new FetchShopOrders($shop, 1, Carbon::now()->subMonths(2)->unix(), Carbon::now()->unix()))->onQueue('pancake');
        }

        (new PostHogService)->capture((string) $request->user()->id, 'onboarding_shop_connected', [
            'workspace_id' => $workspace->id,
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'pages_created' => $createdPages,
        ]);

        return back()->with('success', 'Shop connected! Syncing your data...');
    }

    public function status(Request $request, Workspace $workspace)
    {
        $shop = $workspace->shops()->first();

        if (! $shop) {
            return response()->json(['syncing' => false, 'complete' => false]);
        }

        $complete = $shop->orders_last_synced_at !== null;

        return response()->json([
            'syncing' => ! $complete,
            'complete' => $complete,
        ]);
    }

    public function skip(Request $request, Workspace $workspace)
    {
        // Create free trial subscription if none exists
        if (! $workspace->subscription) {
            $trialPlan = SubscriptionPlan::where('code', SubscriptionPlan::CODE_FREE_TRIAL)->first();
            $trialDays = $trialPlan?->trial_days ?? 30;

            Subscription::create([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $trialPlan?->id,
                'status' => Subscription::STATUS_TRIALING,
                'trial_ends_at' => Carbon::now()->addDays($trialDays),
                'current_period_start' => Carbon::now(),
                'current_period_end' => Carbon::now()->addDays($trialDays),
            ]);
        }

        return redirect()->route('workspace.dashboard', $workspace->slug);
    }
}
