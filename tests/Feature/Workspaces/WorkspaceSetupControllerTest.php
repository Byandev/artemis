<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;

test('create page redirects existing-workspace users to their dashboard', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get('/workspaces/setup')
        ->assertRedirect("/workspaces/{$workspace->slug}/dashboard");
});

test('create page renders for users without workspaces', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/workspaces/setup')
        ->assertOk();
});

test('store creates workspace, attaches owner, sets session, and redirects to onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/workspaces/setup', [
            'name' => 'My First',
            'description' => 'desc',
            'monthly_order_volume' => '500-1000',
        ])
        ->assertRedirect()
        ->assertSessionHas('current_workspace_id');

    $workspace = Workspace::where('name', 'My First')->first();
    expect($workspace)->not->toBeNull();
    expect($workspace->owner_id)->toBe($user->id);
    expect($workspace->hasMember($user))->toBeTrue();
    expect($workspace->monthly_order_volume)->toBe('500-1000');
});

test('store starts a free-trial subscription if the plan exists', function () {
    $plan = SubscriptionPlan::create([
        'name' => 'Free Trial',
        'code' => SubscriptionPlan::CODE_FREE_TRIAL,
        'price_php' => 0,
        'trial_days' => 14,
        'data_retention_months' => 1,
        'analytics_tier' => 'basic',
        'support_tier' => 'community',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/workspaces/setup', ['name' => 'TrialSpace'])
        ->assertRedirect();

    $workspace = Workspace::where('name', 'TrialSpace')->first();
    $sub = Subscription::where('workspace_id', $workspace->id)->first();
    expect($sub)->not->toBeNull();
    expect($sub->subscription_plan_id)->toBe($plan->id);
    expect($sub->status)->toBe(Subscription::STATUS_TRIALING);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/workspaces/setup')
        ->post('/workspaces/setup', ['name' => 'ab']) // less than 3 chars
        ->assertSessionHasErrors('name');
});

test('store rejects invalid monthly_order_volume', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/workspaces/setup')
        ->post('/workspaces/setup', [
            'name' => 'Valid Name',
            'monthly_order_volume' => 'something-weird',
        ])
        ->assertSessionHasErrors('monthly_order_volume');
});

test('store does not create a second workspace if user already has one', function () {
    ['user' => $owner, 'workspace' => $existing] = makeWorkspaceWithOwner();
    $count = Workspace::count();

    $this->actingAs($owner)
        ->post('/workspaces/setup', ['name' => 'Should not happen'])
        ->assertRedirect("/workspaces/{$existing->slug}/dashboard");

    expect(Workspace::count())->toBe($count);
});
