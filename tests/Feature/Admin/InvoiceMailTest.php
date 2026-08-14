<?php

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceIssuedNotification;
use App\Support\InvoiceMailer;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * An issued invoice emails itself, with the PDF attached, to whoever pays for
 * the workspace: its billing address if it has one, its owner if not.
 */

/** An admin, an owner, and a workspace on the free trial ready to upgrade. */
function billingContext(?string $billingEmail = null): array
{
    $admin = User::factory()->create(['is_super_admin' => true]);
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'billing_email' => $billingEmail,
    ]);

    Subscription::create([
        'workspace_id' => $workspace->id,
        'subscription_plan_id' => SubscriptionPlan::where('code', SubscriptionPlan::CODE_FREE_TRIAL)->value('id'),
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDays(30),
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    return ['admin' => $admin, 'owner' => $owner, 'workspace' => $workspace];
}

function upgrade(User $admin, Workspace $workspace)
{
    return test()->actingAs($admin)
        ->from('/admin/workspaces')
        ->put("/admin/workspaces/{$workspace->slug}/subscription", [
            'subscription_plan_id' => SubscriptionPlan::where('code', SubscriptionPlan::CODE_GROWTH)->value('id'),
            'status' => Subscription::STATUS_ACTIVE,
        ]);
}

test('an upgrade emails the invoice to the billing address', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    upgrade($admin, $workspace)->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(
        InvoiceIssuedNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'accounts@company.test'
    );
});

test('with no billing address the invoice goes to the owner', function () {
    Notification::fake();

    ['admin' => $admin, 'owner' => $owner, 'workspace' => $workspace] = billingContext();

    upgrade($admin, $workspace);

    Notification::assertSentOnDemand(
        InvoiceIssuedNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $owner->email
    );
});

test('a blank billing address falls back to the owner rather than sending nowhere', function () {
    ['owner' => $owner, 'workspace' => $workspace] = billingContext('   ');

    expect($workspace->billingEmail())->toBe($owner->email);
});

test('the invoice records the billing address it was raised against', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    upgrade($admin, $workspace);

    expect(Invoice::sole()->bill_to_email)->toBe('accounts@company.test');
});

test('the email carries the invoice PDF as an attachment', function () {
    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    upgrade($admin, $workspace);

    $invoice = Invoice::sole();
    $mail = (new InvoiceIssuedNotification($invoice))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toContain($invoice->number)
        ->and($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['name'])->toBe("{$invoice->number}.pdf")
        ->and($mail->rawAttachments[0]['options']['mime'])->toBe('application/pdf')
        // A real PDF, not an empty string or an error page.
        ->and($mail->rawAttachments[0]['data'])->toStartWith('%PDF-');
});

test('a mail failure leaves the invoice and the plan change in place', function () {
    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    // Nothing is listening on this port, so the send throws.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

    upgrade($admin, $workspace)->assertRedirect()->assertSessionHasNoErrors();

    expect(Invoice::count())->toBe(1)
        ->and($workspace->fresh()->subscription->status)->toBe(Subscription::STATUS_ACTIVE);
});

test('a draft invoice sends nothing', function () {
    Notification::fake();

    ['workspace' => $workspace] = billingContext('accounts@company.test');

    $invoice = Invoice::create([
        'number' => 'INV-TEST-000001',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'issue_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_DRAFT,
    ]);

    expect(InvoiceMailer::send($invoice))->toBeNull();
    Notification::assertNothingSent();
});

test('flipping a draft to sent emails it, and marking it paid does not', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    $invoice = Invoice::create([
        'number' => 'INV-TEST-000002',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'bill_to_email' => 'accounts@company.test',
        'issue_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_DRAFT,
    ]);

    test()->actingAs($admin)->from('/admin/invoices')
        ->patch("/admin/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_SENT]);

    Notification::assertCount(1);

    test()->actingAs($admin)->from('/admin/invoices')
        ->patch("/admin/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_PAID]);

    // Still one — bookkeeping is not a second send.
    Notification::assertCount(1);
});

test('every invoice is copied to the configured address', function () {
    config(['invoice.cc' => 'accounting@artemis.test']);

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    upgrade($admin, $workspace);

    $invoice = Invoice::sole();
    $mail = (new InvoiceIssuedNotification($invoice))
        ->toMail(new AnonymousNotifiable);

    expect($mail->cc)->toBe([['accounting@artemis.test', null]]);
});

test('several copies can be configured, comma separated', function () {
    config(['invoice.cc' => 'accounting@artemis.test, records@artemis.test']);

    ['workspace' => $workspace] = billingContext();

    $invoice = Invoice::create([
        'number' => 'INV-TEST-000030',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'issue_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_SENT,
    ]);

    $mail = (new InvoiceIssuedNotification($invoice))
        ->toMail(new AnonymousNotifiable);

    expect(array_column($mail->cc, 0))
        ->toBe(['accounting@artemis.test', 'records@artemis.test']);
});

test('a malformed copy address is dropped, not allowed to break the send', function () {
    Notification::fake();

    config(['invoice.cc' => 'not-an-address, accounting@artemis.test']);

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    upgrade($admin, $workspace)->assertSessionHasNoErrors();

    $mail = (new InvoiceIssuedNotification(Invoice::sole()))
        ->toMail(new AnonymousNotifiable);

    // The customer's bill still goes out; only the bad entry is discarded.
    expect(array_column($mail->cc, 0))->toBe(['accounting@artemis.test']);
    Notification::assertSentOnDemand(InvoiceIssuedNotification::class);
});

test('the recipient is not also copied to itself', function () {
    config(['invoice.cc' => 'Accounts@Company.test']);

    ['workspace' => $workspace] = billingContext('accounts@company.test');

    $invoice = Invoice::create([
        'number' => 'INV-TEST-000031',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'bill_to_email' => 'accounts@company.test',
        'issue_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_SENT,
    ]);

    // Same address as the To line, differing only in case — one copy, not two.
    $mail = (new InvoiceIssuedNotification($invoice))
        ->toMail(Notification::route('mail', 'accounts@company.test'));

    expect($mail->cc)->toBeEmpty();
});

test('no copy is added when none is configured', function () {
    config(['invoice.cc' => null]);

    ['workspace' => $workspace] = billingContext();

    $invoice = Invoice::create([
        'number' => 'INV-TEST-000032',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'issue_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_SENT,
    ]);

    $mail = (new InvoiceIssuedNotification($invoice))
        ->toMail(new AnonymousNotifiable);

    expect($mail->cc)->toBeEmpty();
});

test('an admin can set and clear a workspace billing email', function () {
    ['admin' => $admin, 'owner' => $owner, 'workspace' => $workspace] = billingContext();

    test()->actingAs($admin)->from('/admin/workspaces')
        ->put("/admin/workspaces/{$workspace->slug}/billing-email", ['billing_email' => 'billing@company.test'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($workspace->fresh()->billing_email)->toBe('billing@company.test');

    test()->actingAs($admin)->from('/admin/workspaces')
        ->put("/admin/workspaces/{$workspace->slug}/billing-email", ['billing_email' => ''])
        ->assertSessionHasNoErrors();

    expect($workspace->fresh()->billing_email)->toBeNull()
        ->and($workspace->fresh()->billingEmail())->toBe($owner->email);
});

test('a malformed billing email is rejected', function () {
    ['admin' => $admin, 'workspace' => $workspace] = billingContext();

    test()->actingAs($admin)->from('/admin/workspaces')
        ->put("/admin/workspaces/{$workspace->slug}/billing-email", ['billing_email' => 'not-an-address'])
        ->assertSessionHasErrors('billing_email');

    expect($workspace->fresh()->billing_email)->toBeNull();
});
