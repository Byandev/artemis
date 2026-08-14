<?php

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceIssuedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * What happens after a renewal invoice is raised: paying it moves the
 * subscription on to the next period, and leaving it unpaid moves the status
 * to past_due so the admin list stops claiming the workspace is active.
 */

/** An admin, plus a workspace on an active paid plan ending on $endsOn. */
function cycleContext(string $endsOn = '2026-10-01'): array
{
    $admin = User::factory()->create(['is_super_admin' => true]);
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    Subscription::create([
        'workspace_id' => $workspace->id,
        'subscription_plan_id' => SubscriptionPlan::where('code', SubscriptionPlan::CODE_GROWTH)->value('id'),
        'status' => Subscription::STATUS_ACTIVE,
        'current_period_start' => Carbon\Carbon::parse($endsOn)->subMonth(),
        'current_period_end' => $endsOn,
    ]);

    return ['admin' => $admin, 'workspace' => $workspace];
}

/** A renewal invoice covering the period ending $periodEnd. */
function renewalInvoiceFor(Workspace $workspace, string $periodEnd, string $number = 'INV-TEST-100001'): Invoice
{
    return Invoice::create([
        'number' => $number,
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'bill_to_email' => 'billing@example.test',
        'issue_date' => Carbon\Carbon::parse($periodEnd)->subDays(7),
        'due_date' => Carbon\Carbon::parse($periodEnd)->addDays(7),
        'period_end' => $periodEnd,
        'line_items' => [],
        'status' => Invoice::STATUS_SENT,
    ]);
}

function markPaid(User $admin, Invoice $invoice)
{
    return test()->actingAs($admin)->from('/admin/invoices')
        ->patch("/admin/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_PAID]);
}

test('paying a renewal moves the subscription to the next period', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = cycleContext('2026-10-01');
    $invoice = renewalInvoiceFor($workspace, '2026-10-01');

    markPaid($admin, $invoice)->assertSessionHasNoErrors();

    $subscription = $workspace->fresh()->subscription;

    expect($subscription->current_period_start->toDateString())->toBe('2026-10-01')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-11-01')
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE);
});

test('the anniversary holds even when they pay late', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = cycleContext('2026-10-01');
    $invoice = renewalInvoiceFor($workspace, '2026-10-01');

    // Money lands on the 20th, nineteen days after the period ended.
    $this->travelTo('2026-10-20 10:00');
    markPaid($admin, $invoice);
    $this->travelBack();

    // Still Nov 1 — anchored to the period, not to the day payment arrived,
    // so the billing date does not drift a little further every month.
    expect($workspace->fresh()->subscription->current_period_end->toDateString())->toBe('2026-11-01');
});

test('paying restores a past-due subscription', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = cycleContext('2026-10-01');
    $workspace->subscription->update(['status' => Subscription::STATUS_PAST_DUE]);

    markPaid($admin, renewalInvoiceFor($workspace, '2026-10-01'));

    $subscription = $workspace->fresh()->subscription;

    expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->isLapsed())->toBeFalse();
});

test('marking the same invoice paid twice does not skip a month', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = cycleContext('2026-10-01');
    $invoice = renewalInvoiceFor($workspace, '2026-10-01');

    markPaid($admin, $invoice);
    // Back to sent, then paid again — a correction, not another month.
    test()->actingAs($admin)->from('/admin/invoices')
        ->patch("/admin/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_SENT]);
    markPaid($admin, $invoice->fresh());

    expect($workspace->fresh()->subscription->current_period_end->toDateString())->toBe('2026-11-01');
});

test('an invoice with no period leaves the subscription alone', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = cycleContext('2026-10-01');

    // A trial-upgrade or one-off invoice — it carries no period_end.
    $invoice = Invoice::create([
        'number' => 'INV-TEST-100009',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'issue_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_SENT,
    ]);

    markPaid($admin, $invoice);

    expect($workspace->fresh()->subscription->current_period_end->toDateString())->toBe('2026-10-01');
});

test('the cycle repeats — paying each renewal sets up the next one', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = cycleContext('2026-10-01');

    // Oct, Nov, Dec — each renewal raised 7 days early, paid, and the period
    // moves on, which is what makes the following one due at all.
    foreach ([['2026-10-01', '2026-09-24'], ['2026-11-01', '2026-10-25'], ['2026-12-01', '2026-11-24']] as $i => [$periodEnd, $raisedOn]) {
        $this->travelTo("{$raisedOn} 08:00");
        $this->artisan('invoices:generate-renewals')->assertSuccessful();

        $invoice = Invoice::whereDate('period_end', $periodEnd)->sole();
        expect($invoice->issue_date->toDateString())->toBe($raisedOn);

        markPaid($admin, $invoice);
    }

    $this->travelBack();

    expect(Invoice::count())->toBe(3)
        ->and($workspace->fresh()->subscription->current_period_end->toDateString())->toBe('2027-01-01');
});

test('the renewal email says it is a renewal, when it renews, and what it covers', function () {
    ['workspace' => $workspace] = cycleContext('2026-10-01');

    $invoice = renewalInvoiceFor($workspace, '2026-10-01');
    $invoice->subscription_plan_id = SubscriptionPlan::where('code', SubscriptionPlan::CODE_GROWTH)->value('id');
    $invoice->total = 8999;
    $invoice->save();

    $mail = (new InvoiceIssuedNotification($invoice->fresh()))->toMail(new AnonymousNotifiable);
    $body = implode("\n", array_merge($mail->introLines, $mail->outroLines));

    expect($mail->subject)->toContain('Renewal invoice')
        ->and($mail->subject)->toContain('Pro plan')
        // It arrives a week before anything is owed, so it has to answer
        // "why am I getting this now?" without the reader opening the PDF.
        ->and($body)->toContain('renews on October 1, 2026')
        ->and($body)->toContain('Oct 1 – Nov 1, 2026')
        ->and($body)->toContain('October 8, 2026')
        ->and($mail->rawAttachments[0]['name'])->toBe("{$invoice->number}.pdf");
});

test('a first invoice off the trial keeps the plain wording', function () {
    ['workspace' => $workspace] = cycleContext('2026-10-01');

    // No period_end — this is the trial conversion, not a renewal.
    $invoice = Invoice::create([
        'number' => 'INV-TEST-100020',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'issue_date' => now(),
        'due_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_SENT,
    ]);

    $mail = (new InvoiceIssuedNotification($invoice))->toMail(new AnonymousNotifiable);
    $body = implode("\n", array_merge($mail->introLines, $mail->outroLines));

    expect($mail->subject)->not->toContain('Renewal')
        ->and($body)->not->toContain('renews on')
        ->and($body)->not->toContain('Covers:')
        // Raised and owed the same day, so it says so in words.
        ->and($body)->toContain('**Due:** today');
});

test('an ended period with an unpaid invoice becomes past due', function () {
    ['workspace' => $workspace] = cycleContext('2026-10-01');
    renewalInvoiceFor($workspace, '2026-10-01');

    $this->travelTo('2026-10-02 00:15');
    $this->artisan('subscriptions:mark-past-due')->assertSuccessful();
    $this->travelBack();

    expect($workspace->fresh()->subscription->status)->toBe(Subscription::STATUS_PAST_DUE);
});

test('a period that was never invoiced is left active and flagged instead', function () {
    ['workspace' => $workspace] = cycleContext('2026-10-01');
    // No invoice at all — the renewal run never fired for this period.

    $this->travelTo('2026-10-02 00:15');
    $this->artisan('subscriptions:mark-past-due')
        ->expectsOutputToContain('no invoice was ever raised')
        ->assertSuccessful();
    $this->travelBack();

    // Not our customer's fault, so not branded a non-payer.
    expect($workspace->fresh()->subscription->status)->toBe(Subscription::STATUS_ACTIVE);
});

test('a period still running is untouched', function () {
    ['workspace' => $workspace] = cycleContext('2026-10-01');
    renewalInvoiceFor($workspace, '2026-10-01');

    $this->travelTo('2026-09-30 00:15');
    $this->artisan('subscriptions:mark-past-due')->assertSuccessful();
    $this->travelBack();

    expect($workspace->fresh()->subscription->status)->toBe(Subscription::STATUS_ACTIVE);
});

test('a paid renewal never goes past due, because the period moved', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = cycleContext('2026-10-01');
    markPaid($admin, renewalInvoiceFor($workspace, '2026-10-01'));

    $this->travelTo('2026-10-02 00:15');
    $this->artisan('subscriptions:mark-past-due')->assertSuccessful();
    $this->travelBack();

    expect($workspace->fresh()->subscription->status)->toBe(Subscription::STATUS_ACTIVE);
});

test('a dry run writes nothing', function () {
    ['workspace' => $workspace] = cycleContext('2026-10-01');
    renewalInvoiceFor($workspace, '2026-10-01');

    $this->travelTo('2026-10-02 00:15');
    $this->artisan('subscriptions:mark-past-due --dry-run')
        ->expectsOutputToContain('would mark past due')
        ->assertSuccessful();
    $this->travelBack();

    expect($workspace->fresh()->subscription->status)->toBe(Subscription::STATUS_ACTIVE);
});
