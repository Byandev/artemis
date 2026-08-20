<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->workspace = Workspace::factory()->create();
    // Plans are seeded by migration, so reuse one rather than building a row
    // that would have to satisfy every non-nullable column.
    $this->plan = SubscriptionPlan::where('code', SubscriptionPlan::CODE_STARTER)->firstOrFail();
    $this->url = route('admin.workspaces.update-subscription', $this->workspace);
});

it('stores the hand-picked period start and end', function () {
    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => '2026-03-01',
            'current_period_end' => '2027-02-28',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $subscription = $this->workspace->fresh()->subscription;

    expect($subscription->current_period_start->toDateString())->toBe('2026-03-01')
        ->and($subscription->current_period_end->toDateString())->toBe('2027-02-28');
});

it('runs the picked end date through the end of that day', function () {
    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => '2026-03-01',
            'current_period_end' => '2026-03-31',
        ])
        ->assertRedirect();

    $end = $this->workspace->fresh()->subscription->current_period_end;

    // Stored at 23:59:59 so a subscription "ending today" is not already lapsed.
    expect($end->format('H:i:s'))->toBe('23:59:59');
});

it('keeps trial_ends_at in step with a hand-picked end date on a trial', function () {
    $end = Carbon::now()->addMonths(2)->toDateString();

    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'trialing',
            'current_period_start' => Carbon::now()->toDateString(),
            'current_period_end' => $end,
        ])
        ->assertRedirect();

    $subscription = $this->workspace->fresh()->subscription;

    // trial_ends_at tracks the picked end, so the trial is still live.
    expect($subscription->trial_ends_at->toDateString())->toBe($end)
        ->and($subscription->current_period_end->toDateString())->toBe($end)
        ->and($subscription->isLapsed())->toBeFalse();
});

it('lapses a trial when the picked end date is already past', function () {
    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'trialing',
            'current_period_start' => Carbon::now()->subMonths(3)->toDateString(),
            'current_period_end' => Carbon::now()->subMonth()->toDateString(),
        ])
        ->assertRedirect();

    // Proves the picked date actually drives access, not just display.
    expect($this->workspace->fresh()->subscription->isLapsed())->toBeTrue();
});

it('keeps an active subscription live through the end of the picked day', function () {
    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => Carbon::now()->subMonth()->toDateString(),
            // Ends today — endOfDay() storage is what keeps this from lapsing.
            'current_period_end' => Carbon::now()->toDateString(),
        ])
        ->assertRedirect();

    expect($this->workspace->fresh()->subscription->isLapsed())->toBeFalse();
});

it('clears trial_ends_at when the status is active', function () {
    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => '2026-03-01',
            'current_period_end' => '2027-02-28',
        ])
        ->assertRedirect();

    expect($this->workspace->fresh()->subscription->trial_ends_at)->toBeNull();
});

it('falls back to one month from today when the dates are left blank on active', function () {
    Carbon::setTestNow('2026-05-10 09:30:00');

    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => null,
            'current_period_end' => null,
        ])
        ->assertRedirect();

    $subscription = $this->workspace->fresh()->subscription;

    expect($subscription->current_period_start->toDateString())->toBe('2026-05-10')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-06-10');

    Carbon::setTestNow();
});

it('falls back to the plan trial length when the dates are left blank on a trial', function () {
    Carbon::setTestNow('2026-05-10 09:30:00');

    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'trialing',
            'current_period_start' => null,
            'current_period_end' => null,
        ])
        ->assertRedirect();

    $subscription = $this->workspace->fresh()->subscription;

    $expected = Carbon::parse('2026-05-10')
        ->addDays($this->plan->trial_days ?? 30)
        ->toDateString();

    expect($subscription->current_period_end->toDateString())->toBe($expected)
        ->and($subscription->trial_ends_at->toDateString())->toBe($expected);

    Carbon::setTestNow();
});

it('derives the end date from a picked start when only the start is given', function () {
    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => '2026-09-01',
            'current_period_end' => null,
        ])
        ->assertRedirect();

    $subscription = $this->workspace->fresh()->subscription;

    expect($subscription->current_period_start->toDateString())->toBe('2026-09-01')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-10-01');
});

it('rejects an end date that falls before the start date', function () {
    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => '2026-06-01',
            'current_period_end' => '2026-05-01',
        ])
        ->assertSessionHasErrors('current_period_end');

    expect($this->workspace->fresh()->subscription)->toBeNull();
});

it('rejects a non-date value', function () {
    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => 'not-a-date',
        ])
        ->assertSessionHasErrors('current_period_start');
});

it('applies picked dates to an existing subscription', function () {
    $existing = Subscription::create([
        'workspace_id' => $this->workspace->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_start' => '2026-01-01',
        'current_period_end' => '2026-01-31',
    ]);

    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => '2026-07-01',
            'current_period_end' => '2026-12-31',
        ])
        ->assertRedirect();

    $existing->refresh();

    expect($existing->current_period_start->toDateString())->toBe('2026-07-01')
        ->and($existing->current_period_end->toDateString())->toBe('2026-12-31')
        // Updated in place rather than a second row being created.
        ->and(Subscription::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

it('leaves existing dates alone for a non-active status when none are picked', function () {
    $existing = Subscription::create([
        'workspace_id' => $this->workspace->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_start' => '2026-01-01',
        'current_period_end' => '2026-01-31',
    ]);

    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'past_due',
        ])
        ->assertRedirect();

    $existing->refresh();

    expect($existing->status)->toBe('past_due')
        ->and($existing->current_period_start->toDateString())->toBe('2026-01-01')
        ->and($existing->current_period_end->toDateString())->toBe('2026-01-31');
});

it('honours picked dates even for a non-active status', function () {
    Subscription::create([
        'workspace_id' => $this->workspace->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_start' => '2026-01-01',
        'current_period_end' => '2026-01-31',
    ]);

    $this->actingAs($this->admin)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'past_due',
            'current_period_start' => '2026-02-01',
            'current_period_end' => '2026-02-28',
        ])
        ->assertRedirect();

    $subscription = $this->workspace->fresh()->subscription;

    expect($subscription->current_period_start->toDateString())->toBe('2026-02-01')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-02-28');
});

it('does not let a non-admin change the subscription period', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put($this->url, [
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_start' => '2026-03-01',
            'current_period_end' => '2027-02-28',
        ])
        ->assertRedirect(route('dashboard'));

    expect($this->workspace->fresh()->subscription)->toBeNull();
});
