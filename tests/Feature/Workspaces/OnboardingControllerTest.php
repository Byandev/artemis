<?php

use App\Models\Page;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

test('onboarding page renders for new workspaces', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/onboarding")
        ->assertOk();
});

test('onboarding redirects to dashboard if a page already exists', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Page::factory()->forWorkspace($workspace)->create();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/onboarding")
        ->assertRedirect("/workspaces/{$workspace->slug}/dashboard");
});

test('onboarding store creates page and shop after upstream API confirms', function () {
    Bus::fake();
    SubscriptionPlan::create([
        'code' => SubscriptionPlan::CODE_FREE_TRIAL, 'name' => 'Free Trial',
        'price_php' => 0, 'trial_days' => 14, 'data_retention_months' => 1,
        'analytics_tier' => 'basic', 'support_tier' => 'community',
    ]);
    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => [
                'name' => 'Test Shop',
                'avatar_url' => 'https://cdn.example.test/a.png',
                'pages' => [['id' => 9999, 'name' => 'My Page']],
            ],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/onboarding")
        ->post("/workspaces/{$workspace->slug}/onboarding", [
            'page_id' => 9999,
            'shop_id' => 555,
            'page_name' => 'My Page',
            'pos_token' => 'valid-token',
        ])
        ->assertRedirect();

    expect(Page::where('id', 9999)->where('workspace_id', $workspace->id)->exists())->toBeTrue();
    expect(Shop::where('id', 555)->where('workspace_id', $workspace->id)->exists())->toBeTrue();
    Bus::assertDispatched(\Modules\Pancake\Jobs\FetchPageOrders::class);
    Bus::assertDispatched(\Modules\Pancake\Jobs\FetchShopCustomers::class);
    Bus::assertDispatched(\Modules\Pancake\Jobs\FetchShopUsers::class);
});

test('onboarding store creates a free trial subscription if none exists', function () {
    Bus::fake();
    SubscriptionPlan::create([
        'code' => SubscriptionPlan::CODE_FREE_TRIAL,
        'name' => 'Free Trial',
        'price_php' => 0,
        'trial_days' => 14,
        'data_retention_months' => 1,
        'analytics_tier' => 'basic',
        'support_tier' => 'community',
    ]);

    Http::fake([
        'pos.pages.fm/*' => Http::response([
            'shop' => ['name' => 'S', 'pages' => [['id' => 9999, 'name' => 'P']]],
        ], 200),
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/onboarding")
        ->post("/workspaces/{$workspace->slug}/onboarding", [
            'page_id' => 9999,
            'shop_id' => 555,
            'page_name' => 'P',
            'pos_token' => 'token',
        ])
        ->assertRedirect();

    expect(Subscription::where('workspace_id', $workspace->id)->exists())->toBeTrue();
});

test('onboarding store does not duplicate subscription if one already exists', function () {
    Bus::fake();
    Http::fake(['pos.pages.fm/*' => Http::response([
        'shop' => ['name' => 'S', 'pages' => [['id' => 9999, 'name' => 'P']]],
    ], 200)]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $plan = SubscriptionPlan::create([
        'code' => SubscriptionPlan::CODE_FREE_TRIAL,
        'name' => 'Free Trial', 'price_php' => 0, 'trial_days' => 14,
        'data_retention_months' => 1, 'analytics_tier' => 'basic', 'support_tier' => 'community',
    ]);
    Subscription::create([
        'workspace_id' => $workspace->id,
        'subscription_plan_id' => $plan->id,
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDays(7),
        'current_period_start' => now(),
        'current_period_end' => now()->addDays(7),
    ]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/onboarding")
        ->post("/workspaces/{$workspace->slug}/onboarding", [
            'page_id' => 9999,
            'shop_id' => 555,
            'page_name' => 'P',
            'pos_token' => 'token',
        ])
        ->assertRedirect();

    expect(Subscription::where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('onboarding store rejects when Pancake API fails', function () {
    Bus::fake();
    Http::fake(['pos.pages.fm/*' => Http::response(['error' => 'invalid'], 401)]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/onboarding")
        ->post("/workspaces/{$workspace->slug}/onboarding", [
            'page_id' => 9999,
            'shop_id' => 555,
            'page_name' => 'P',
            'pos_token' => 'bad',
        ])
        ->assertSessionHasErrors('pos_token');

    expect(Page::where('id', 9999)->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('onboarding store rejects when page id is not in shop response', function () {
    Bus::fake();
    Http::fake(['pos.pages.fm/*' => Http::response([
        'shop' => ['name' => 'S', 'pages' => [['id' => 1, 'name' => 'X']]],
    ], 200)]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/onboarding")
        ->post("/workspaces/{$workspace->slug}/onboarding", [
            'page_id' => 9999,
            'shop_id' => 555,
            'page_name' => 'P',
            'pos_token' => 'token',
        ])
        ->assertSessionHasErrors('page_id');
});

test('onboarding store validates required fields', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/onboarding")
        ->post("/workspaces/{$workspace->slug}/onboarding", [])
        ->assertSessionHasErrors(['page_id', 'shop_id', 'page_name', 'pos_token']);
});

test('onboarding skip creates a free trial subscription if none exists', function () {
    SubscriptionPlan::create([
        'code' => SubscriptionPlan::CODE_FREE_TRIAL, 'name' => 'Free Trial',
        'price_php' => 0, 'trial_days' => 14, 'data_retention_months' => 1,
        'analytics_tier' => 'basic', 'support_tier' => 'community',
    ]);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/onboarding/skip")
        ->assertRedirect("/workspaces/{$workspace->slug}/dashboard");

    expect(Subscription::where('workspace_id', $workspace->id)->exists())->toBeTrue();
});

test('onboarding status returns syncing/complete state', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // No page yet
    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/onboarding/status")
        ->assertOk()
        ->assertJson(['syncing' => false, 'complete' => false]);

    // Page exists, never synced
    $page = Page::factory()->forWorkspace($workspace)->neverSynced()->create();
    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/onboarding/status")
        ->assertOk()
        ->assertJson(['syncing' => true, 'complete' => false]);

    // Page synced
    $page->update(['orders_last_synced_at' => now()]);
    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/onboarding/status")
        ->assertOk()
        ->assertJson(['syncing' => false, 'complete' => true]);
});

test('guest is redirected to login from onboarding endpoints', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->get("/workspaces/{$workspace->slug}/onboarding")
        ->assertRedirect('/login');
});
