<?php

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;

/**
 * Upgrading a workspace's plan from the admin area raises the invoice for it.
 * A workspace coming off the free trial owes for time it has already had, so
 * that invoice is due today; anyone else gets the standard terms.
 */

/** The plan catalog is seeded by migration, so look plans up rather than create them. */
function plan(string $code): SubscriptionPlan
{
    return SubscriptionPlan::where('code', $code)->firstOrFail();
}

/** An admin, a workspace with an owner, and the workspace's starting subscription. */
function adminContext(?string $onPlan = SubscriptionPlan::CODE_FREE_TRIAL, string $status = Subscription::STATUS_TRIALING): array
{
    $admin = User::factory()->create(['is_super_admin' => true]);
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    if ($onPlan) {
        Subscription::create([
            'workspace_id' => $workspace->id,
            'subscription_plan_id' => plan($onPlan)->id,
            'status' => $status,
            'trial_ends_at' => $status === Subscription::STATUS_TRIALING ? now()->addDays(30) : null,
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
        ]);
    }

    return ['admin' => $admin, 'owner' => $owner, 'workspace' => $workspace];
}

/** Move the workspace onto a plan the way the admin modal does. */
function changePlan(User $admin, Workspace $workspace, string $code, string $status = Subscription::STATUS_ACTIVE)
{
    return test()->actingAs($admin)
        ->from('/admin/workspaces')
        ->put("/admin/workspaces/{$workspace->slug}/subscription", [
            'subscription_plan_id' => plan($code)->id,
            'status' => $status,
        ]);
}

test('upgrading from the free trial raises an invoice due today', function () {
    ['admin' => $admin, 'owner' => $owner, 'workspace' => $workspace] = adminContext();

    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invoice = Invoice::where('workspace_id', $workspace->id)->sole();

    expect($invoice->due_date->toDateString())->toBe(now()->toDateString())
        ->and($invoice->issue_date->toDateString())->toBe(now()->toDateString())
        ->and($invoice->subscription_plan_id)->toBe(plan(SubscriptionPlan::CODE_GROWTH)->id)
        ->and((float) $invoice->total)->toBe((float) plan(SubscriptionPlan::CODE_GROWTH)->price_php)
        ->and($invoice->bill_to_name)->toBe($owner->name)
        ->and($invoice->bill_to_email)->toBe($owner->email)
        ->and($invoice->status)->toBe(Invoice::STATUS_SENT)
        ->and($invoice->number)->toStartWith('INV-'.now()->format('Y').'-')
        ->and($invoice->line_items[0]['description'])->toContain(plan(SubscriptionPlan::CODE_GROWTH)->name);
});

test('a trialing workspace on a paid plan also counts as coming off trial', function () {
    // The modal only pairs `trialing` with free_trial, but the status is set
    // by hand often enough that it has to carry the same meaning.
    ['admin' => $admin, 'workspace' => $workspace] = adminContext(
        SubscriptionPlan::CODE_STARTER,
        Subscription::STATUS_TRIALING
    );

    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH);

    expect(Invoice::sole()->due_date->toDateString())->toBe(now()->toDateString());
});

test('a paid-to-paid plan change raises nothing — the renewal run bills it', function () {
    ['admin' => $admin, 'workspace' => $workspace] = adminContext(
        SubscriptionPlan::CODE_STARTER,
        Subscription::STATUS_ACTIVE
    );

    changePlan($admin, $workspace, SubscriptionPlan::CODE_SCALE);

    // Not a new sale — the subscription carries on, and
    // invoices:generate-renewals bills it 7 days before the period ends.
    expect(Invoice::count())->toBe(0);
});

test('a workspace with no subscription at all raises nothing', function () {
    ['admin' => $admin, 'workspace' => $workspace] = adminContext(onPlan: null);

    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH);

    expect(Invoice::count())->toBe(0);
});

test('moving onto the free trial raises nothing', function () {
    ['admin' => $admin, 'workspace' => $workspace] = adminContext(
        SubscriptionPlan::CODE_STARTER,
        Subscription::STATUS_ACTIVE
    );

    changePlan($admin, $workspace, SubscriptionPlan::CODE_FREE_TRIAL, Subscription::STATUS_TRIALING);

    expect(Invoice::count())->toBe(0);
});

test('a zero-priced plan raises nothing', function () {
    // Enterprise is quoted by hand off-system, so its ₱0 is a placeholder.
    ['admin' => $admin, 'workspace' => $workspace] = adminContext();

    changePlan($admin, $workspace, SubscriptionPlan::CODE_ENTERPRISE);

    expect(Invoice::count())->toBe(0);
});

test('a status that is not active raises nothing', function (string $status) {
    ['admin' => $admin, 'workspace' => $workspace] = adminContext();

    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH, $status);

    expect(Invoice::count())->toBe(0);
})->with(['trialing', 'past_due', 'canceled', 'expired']);

test('saving the upgrade twice does not bill twice', function () {
    ['admin' => $admin, 'workspace' => $workspace] = adminContext();

    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH);
    // The second Save is no longer "from trial" — it is already converted.
    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH);

    expect(Invoice::count())->toBe(1);
});

test('reviving a past-due subscription raises nothing', function () {
    ['admin' => $admin, 'workspace' => $workspace] = adminContext(
        SubscriptionPlan::CODE_GROWTH,
        Subscription::STATUS_PAST_DUE
    );

    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH);

    // The unpaid invoice for that period already exists; the revived
    // subscription is billed again by the renewal run, not here.
    expect(Invoice::count())->toBe(0);
});

test('each upgrade takes the next invoice number', function () {
    ['admin' => $admin, 'workspace' => $workspace] = adminContext();
    ['admin' => $admin2, 'workspace' => $other] = adminContext();

    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH);
    changePlan($admin2, $other, SubscriptionPlan::CODE_SCALE);

    expect(Invoice::orderBy('id')->pluck('number')->all())->toBe([
        sprintf('INV-%d-%06d', now()->year, 1),
        sprintf('INV-%d-%06d', now()->year, 2),
    ]);
});

test('the subscription still moves even though an invoice went with it', function () {
    ['admin' => $admin, 'workspace' => $workspace] = adminContext();

    changePlan($admin, $workspace, SubscriptionPlan::CODE_GROWTH);

    $subscription = $workspace->fresh()->subscription;

    expect($subscription->subscription_plan_id)->toBe(plan(SubscriptionPlan::CODE_GROWTH)->id)
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->trial_ends_at)->toBeNull();
});

test('a non-admin cannot trigger a plan change or an invoice', function () {
    ['workspace' => $workspace] = adminContext();
    $nobody = User::factory()->create(['is_super_admin' => false]);

    test()->actingAs($nobody)
        ->put("/admin/workspaces/{$workspace->slug}/subscription", [
            'subscription_plan_id' => plan(SubscriptionPlan::CODE_GROWTH)->id,
            'status' => Subscription::STATUS_ACTIVE,
        ])
        ->assertRedirect();

    expect(Invoice::count())->toBe(0)
        ->and($workspace->fresh()->subscription->status)->toBe(Subscription::STATUS_TRIALING);
});
