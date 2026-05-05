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
use Modules\Pancake\Jobs\FetchPageOrders;
use Modules\Pancake\Jobs\FetchShopCustomers;
use Modules\Pancake\Jobs\FetchShopUsers;

class OnboardingController extends Controller
{
    public function create(Request $request, Workspace $workspace)
    {
        // Skip if workspace already has pages (onboarding already done)
        if ($workspace->pages()->exists()) {
            return redirect()->route('workspace.dashboard', $workspace->slug);
        }

        return Inertia::render('workspaces/onboarding', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'page_id' => ['required', 'integer'],
            'shop_id' => ['required', 'integer'],
            'page_name' => ['required', 'string', 'max:255'],
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
        $pageData = collect($resJson['shop']['pages'] ?? [])->firstWhere('id', $validated['page_id']);

        if (! $pageData) {
            throw ValidationException::withMessages(['page_id' => 'Page not found for this shop. Please check the Page ID.']);
        }

        // Create or get Shop
        $shop = Shop::firstOrCreate([
            'id' => $validated['shop_id'],
            'workspace_id' => $workspace->id,
        ], [
            'name' => $resJson['shop']['name'] ?? $validated['page_name'],
            'avatar_url' => $resJson['shop']['avatar_url'] ?? null,
        ]);

        // Create Page
        $page = Page::create([
            'id' => $validated['page_id'],
            'workspace_id' => $workspace->id,
            'owner_id' => $request->user()->id,
            'shop_id' => $validated['shop_id'],
            'name' => $validated['page_name'],
            'pos_token' => $validated['pos_token'],
            'status' => 'active',
        ]);

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

        // Dispatch fetch jobs
        $now = Carbon::now();
        dispatch(new FetchPageOrders($page, 1, $now->copy()->subMonths(3)->unix(), $now->unix()))->onQueue('pancake');
        dispatch(new FetchShopCustomers($shop, 1, $now->copy()->subMonths(3)->unix(), $now->unix()))->onQueue('pancake');
        dispatch(new FetchShopUsers($shop))->onQueue('pancake');

        (new PostHogService)->capture((string) $request->user()->id, 'onboarding_page_connected', [
            'workspace_id' => $workspace->id,
            'page_id' => $page->id,
            'page_name' => $page->name,
            'shop_id' => $shop->id,
        ]);

        return redirect()
            ->route('workspace.dashboard', $workspace->slug)
            ->with('success', 'Page connected! Syncing your data...');
    }

    public function status(Request $request, Workspace $workspace)
    {
        $page = $workspace->pages()->first();

        if (! $page) {
            return response()->json(['syncing' => false, 'complete' => false]);
        }

        $complete = $page->orders_last_synced_at !== null;

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
