<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;

function makeTrialPlan(): SubscriptionPlan
{
    return SubscriptionPlan::create([
        'code' => SubscriptionPlan::CODE_FREE_TRIAL, 'name' => 'Free Trial',
        'price_php' => 0, 'trial_days' => 14, 'data_retention_months' => 1,
        'analytics_tier' => 'basic', 'support_tier' => 'community',
    ]);
}

test('marks trial subscriptions whose trial ends in the past as expired', function () {
    $plan = makeTrialPlan();
    $w1 = Workspace::factory()->create();
    $w2 = Workspace::factory()->create();
    $w3 = Workspace::factory()->create();

    Subscription::create([
        'workspace_id' => $w1->id,
        'subscription_plan_id' => $plan->id,
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->subDay(),
        'current_period_start' => now()->subDays(15),
        'current_period_end' => now()->subDay(),
    ]);
    Subscription::create([
        'workspace_id' => $w2->id,
        'subscription_plan_id' => $plan->id,
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDay(),
        'current_period_start' => now()->subDays(13),
        'current_period_end' => now()->addDay(),
    ]);
    Subscription::create([
        'workspace_id' => $w3->id,
        'subscription_plan_id' => $plan->id,
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->subDays(2),
        'current_period_start' => now()->subDays(16),
        'current_period_end' => now()->subDays(2),
    ]);

    $this->artisan('subscriptions:expire-trials')
        ->expectsOutputToContain('Expired 2 trial subscription(s).')
        ->assertSuccessful();

    expect(Subscription::where('workspace_id', $w1->id)->first()->status)->toBe(Subscription::STATUS_EXPIRED);
    expect(Subscription::where('workspace_id', $w2->id)->first()->status)->toBe(Subscription::STATUS_TRIALING);
    expect(Subscription::where('workspace_id', $w3->id)->first()->status)->toBe(Subscription::STATUS_EXPIRED);
});

test('does not change non-trialing subscriptions', function () {
    $plan = makeTrialPlan();
    $w = Workspace::factory()->create();

    Subscription::create([
        'workspace_id' => $w->id,
        'subscription_plan_id' => $plan->id,
        'status' => Subscription::STATUS_EXPIRED,
        'trial_ends_at' => now()->subDays(10),
        'current_period_start' => now()->subDays(20),
        'current_period_end' => now()->subDays(10),
    ]);

    $this->artisan('subscriptions:expire-trials')->assertSuccessful();

    // Still expired (no rewriting), and the count of expired updates is 0
    expect(Subscription::where('workspace_id', $w->id)->first()->status)->toBe(Subscription::STATUS_EXPIRED);
});

test('reports zero when nothing to expire', function () {
    $this->artisan('subscriptions:expire-trials')
        ->expectsOutputToContain('Expired 0 trial subscription(s).')
        ->assertSuccessful();
});
