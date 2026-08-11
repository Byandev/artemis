<?php

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceIssuedNotification;
use App\Support\InvoiceMailer;
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
    $mail = (new InvoiceIssuedNotification($invoice))->toMail($invoice);

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

test('an issued invoice can be resent from the invoices page', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    upgrade($admin, $workspace);
    $invoice = Invoice::sole();

    test()->actingAs($admin)->from('/admin/invoices')
        ->post("/admin/invoices/{$invoice->id}/resend")
        ->assertRedirect()
        ->assertSessionHas('success', "Invoice {$invoice->number} emailed to accounts@company.test.");

    // Once when raised, once on the resend.
    Notification::assertCount(2);
});

test('a paid invoice can still be resent as a copy', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    $invoice = Invoice::create([
        'number' => 'INV-TEST-000003',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'bill_to_email' => 'accounts@company.test',
        'issue_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_PAID,
        'paid_at' => now(),
    ]);

    test()->actingAs($admin)->from('/admin/invoices')
        ->post("/admin/invoices/{$invoice->id}/resend")
        ->assertSessionHas('success');

    Notification::assertCount(1);
});

test('a draft cannot be resent', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    $invoice = Invoice::create([
        'number' => 'INV-TEST-000004',
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'issue_date' => now(),
        'line_items' => [],
        'status' => Invoice::STATUS_DRAFT,
    ]);

    test()->actingAs($admin)->from('/admin/invoices')
        ->post("/admin/invoices/{$invoice->id}/resend")
        ->assertSessionHas('error');

    Notification::assertNothingSent();
});

test('resending falls back to the owner when the workspace has no billing email', function () {
    Notification::fake();

    ['admin' => $admin, 'owner' => $owner, 'workspace' => $workspace] = billingContext();

    upgrade($admin, $workspace);
    $invoice = Invoice::sole();

    test()->actingAs($admin)->from('/admin/invoices')
        ->post("/admin/invoices/{$invoice->id}/resend")
        ->assertSessionHas('success', "Invoice {$invoice->number} emailed to {$owner->email}.");
});

test('a non-admin cannot resend an invoice', function () {
    Notification::fake();

    ['admin' => $admin, 'workspace' => $workspace] = billingContext('accounts@company.test');

    upgrade($admin, $workspace);
    $invoice = Invoice::sole();
    Notification::fake(); // reset the count from the raise above

    test()->actingAs(User::factory()->create(['is_super_admin' => false]))
        ->post("/admin/invoices/{$invoice->id}/resend")
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('the invoices list carries the address a resend would go to', function () {
    ['admin' => $admin, 'owner' => $owner, 'workspace' => $workspace] = billingContext();

    upgrade($admin, $workspace);

    test()->actingAs($admin)
        ->get('/admin/invoices')
        ->assertInertia(fn ($page) => $page
            ->where('invoices.data.0.recipient', $owner->email)
        );
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
