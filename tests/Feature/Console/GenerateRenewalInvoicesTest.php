<?php

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceIssuedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;

/**
 * Every active subscription is billed a week before its period ends, keyed off
 * `current_period_end`. Running the command twice must never bill twice.
 */

/** A workspace on a paid plan whose period ends $inDays from today. */
function renewingIn(int $inDays, string $code = SubscriptionPlan::CODE_GROWTH, string $status = Subscription::STATUS_ACTIVE): Workspace
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    Subscription::create([
        'workspace_id' => $workspace->id,
        'subscription_plan_id' => SubscriptionPlan::where('code', $code)->value('id'),
        'status' => $status,
        'trial_ends_at' => null,
        'current_period_start' => now()->subDays(30 - $inDays),
        'current_period_end' => now()->addDays($inDays),
    ]);

    return $workspace;
}

test('a subscription ending in seven days is invoiced', function () {
    Notification::fake();

    $workspace = renewingIn(7);

    $this->artisan('invoices:generate-renewals')->assertSuccessful();

    $invoice = Invoice::where('workspace_id', $workspace->id)->sole();

    expect($invoice->issue_date->toDateString())->toBe(now()->toDateString())
        // Renewal date + 7 days: raised a week early, payable a week after.
        ->and($invoice->due_date->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and($invoice->period_end->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($invoice->status)->toBe(Invoice::STATUS_SENT)
        ->and($invoice->line_items[0]['description'])->toContain('renewal');

    Notification::assertSentOnDemand(InvoiceIssuedNotification::class);
});

test('only the seventh day before renewal triggers, no other day', function (int $inDays) {
    Notification::fake();

    renewingIn($inDays);

    $this->artisan('invoices:generate-renewals')->assertSuccessful();

    // One invoice per renewal, raised once, on one morning only — so a
    // subscription in its final week is not emailed again each day.
    expect(Invoice::count())->toBe(0);
})->with([
    'eight days out' => 8,
    'six days out' => 6,
    'three days out' => 3,
    'renewing tomorrow' => 1,
    'renewing today' => 0,
    'thirty days out' => 30,
]);

test('a subscription is billed once across the whole week before it renews', function () {
    Notification::fake();

    $workspace = renewingIn(7);

    // Stand in for the scheduler running every morning from day 7 down to day 0.
    foreach (range(0, 7) as $ignored) {
        $this->artisan('invoices:generate-renewals')->assertSuccessful();
    }

    expect(Invoice::where('workspace_id', $workspace->id)->count())->toBe(1);
    Notification::assertCount(1);
});

/**
 * The full lifecycle, on real calendar dates:
 *
 *   Aug 1   30-day trial starts
 *   Sep 1   trial over, admin converts → invoice due the same day
 *   Sep 24  seven days before the period ends → renewal invoice
 *   Oct 1   period ends
 */
test('trial on Aug 1 bills Sep 1, then renews on the Oct 1 cycle', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_super_admin' => true]);
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    // ── Aug 1: workspace created, on the free trial ──────────────────
    $this->travelTo('2026-08-01 09:00');

    Subscription::create([
        'workspace_id' => $workspace->id,
        'subscription_plan_id' => SubscriptionPlan::where('code', SubscriptionPlan::CODE_FREE_TRIAL)->value('id'),
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDays(30),
        'current_period_start' => now(),
        'current_period_end' => now()->addDays(30),
    ]);

    // ── Sep 1: trial is over, admin puts them on Pro ─────────────────
    $this->travelTo('2026-09-01 09:00');

    $this->actingAs($admin)->from('/admin/workspaces')
        ->put("/admin/workspaces/{$workspace->slug}/subscription", [
            'subscription_plan_id' => SubscriptionPlan::where('code', SubscriptionPlan::CODE_GROWTH)->value('id'),
            'status' => Subscription::STATUS_ACTIVE,
        ])->assertSessionHasNoErrors();

    $first = Invoice::sole();

    expect($first->issue_date->toDateString())->toBe('2026-09-01')
        // First payment: due the same day the paid period begins.
        ->and($first->due_date->toDateString())->toBe('2026-09-01')
        ->and($first->line_items[0]['description'])->toContain('subscription');

    // The paid month runs Sep 1 → Oct 1.
    expect($workspace->fresh()->subscription->current_period_end->toDateString())->toBe('2026-10-01');

    // ── Sep 23: still one day early, nothing happens ─────────────────
    $this->travelTo('2026-09-23 08:00');
    $this->artisan('invoices:generate-renewals')->assertSuccessful();
    expect(Invoice::count())->toBe(1);

    // ── Sep 24: exactly seven days out — the renewal invoice ─────────
    $this->travelTo('2026-09-24 08:00');
    $this->artisan('invoices:generate-renewals')->assertSuccessful();

    $renewal = Invoice::where('id', '!=', $first->id)->sole();

    expect($renewal->issue_date->toDateString())->toBe('2026-09-24')
        ->and($renewal->period_end->toDateString())->toBe('2026-10-01')
        // Renewal date + 7 days.
        ->and($renewal->due_date->toDateString())->toBe('2026-10-08')
        ->and($renewal->line_items[0]['description'])->toContain('renewal');

    // ── Sep 25 – Oct 1: no second invoice, no second email ───────────
    foreach (['2026-09-25', '2026-09-28', '2026-10-01'] as $day) {
        $this->travelTo("{$day} 08:00");
        $this->artisan('invoices:generate-renewals')->assertSuccessful();
    }

    expect(Invoice::count())->toBe(2);
    Notification::assertCount(2);

    $this->travelBack();
});

test('a period that has already ended is not invoiced', function () {
    Notification::fake();

    renewingIn(-1);

    $this->artisan('invoices:generate-renewals')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

test('the database refuses a second invoice for the same renewal', function () {
    $workspace = renewingIn(7);
    $renewal = now()->addDays(7)->toDateString();

    $make = fn (string $number) => Invoice::create([
        'number' => $number,
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'issue_date' => now(),
        'period_end' => $renewal,
        'line_items' => [],
        'status' => Invoice::STATUS_SENT,
    ]);

    $make('INV-TEST-000001');

    expect(fn () => $make('INV-TEST-000002'))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('manual invoices carry no period and can pile up freely', function () {
    $workspace = renewingIn(7);

    // Null period_end must not collide under the unique index.
    foreach (['INV-TEST-000003', 'INV-TEST-000004'] as $number) {
        Invoice::create([
            'number' => $number,
            'workspace_id' => $workspace->id,
            'bill_to_name' => 'Someone',
            'issue_date' => now(),
            'line_items' => [],
            'status' => Invoice::STATUS_DRAFT,
        ]);
    }

    expect(Invoice::whereNull('period_end')->count())->toBe(2);
});

test('a next-period renewal still bills after the current one was invoiced', function () {
    Notification::fake();

    $workspace = renewingIn(7);

    $this->artisan('invoices:generate-renewals')->assertSuccessful();

    // The period rolls over; the following one comes due in its own right.
    $workspace->subscription->update([
        'current_period_start' => now()->addDays(7),
        'current_period_end' => now()->addDays(37),
    ]);

    $this->artisan('invoices:generate-renewals --days=37')->assertSuccessful();

    expect(Invoice::count())->toBe(2)
        ->and(Invoice::orderBy('id')->pluck('period_end')->map->toDateString()->all())
        ->toBe([now()->addDays(7)->toDateString(), now()->addDays(37)->toDateString()]);
});

test('only active subscriptions are billed', function (string $status) {
    Notification::fake();

    renewingIn(7, status: $status);

    $this->artisan('invoices:generate-renewals')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
})->with(['trialing', 'past_due', 'canceled', 'expired']);

test('zero-priced plans are skipped', function (string $code) {
    Notification::fake();

    renewingIn(7, $code);

    $this->artisan('invoices:generate-renewals')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
})->with([SubscriptionPlan::CODE_FREE_TRIAL, SubscriptionPlan::CODE_ENTERPRISE]);

test('a dry run writes nothing and sends nothing', function () {
    Notification::fake();

    renewingIn(7);

    $this->artisan('invoices:generate-renewals --dry-run')
        ->expectsOutputToContain('would bill')
        ->assertSuccessful();

    expect(Invoice::count())->toBe(0);
    Notification::assertNothingSent();
});

test('the lead time can be overridden to catch up a missed run', function () {
    Notification::fake();

    // The scheduler was down on this one's seventh-day mark; it is now 3 out.
    renewingIn(3);

    $this->artisan('invoices:generate-renewals')->assertSuccessful();
    expect(Invoice::count())->toBe(0);

    $this->artisan('invoices:generate-renewals --days=3')->assertSuccessful();
    expect(Invoice::count())->toBe(1);
});

test('a mail failure still leaves the invoice raised', function () {
    renewingIn(7);

    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

    $this->artisan('invoices:generate-renewals')->assertSuccessful();

    expect(Invoice::count())->toBe(1);
});
