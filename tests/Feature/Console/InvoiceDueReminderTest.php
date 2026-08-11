<?php

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceDueNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * One reminder, on the day an unpaid invoice falls due, and never again.
 */

/** An invoice falling due on $dueOn, in whatever state. */
function invoiceDue(string $dueOn, string $status = Invoice::STATUS_SENT, ?string $periodEnd = null): Invoice
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'billing_email' => 'accounts@company.test',
    ]);

    return Invoice::create([
        'number' => 'INV-TEST-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Someone',
        'bill_to_email' => 'accounts@company.test',
        'issue_date' => Carbon\Carbon::parse($dueOn)->subDays(14),
        'due_date' => $dueOn,
        'period_end' => $periodEnd,
        'line_items' => [],
        'total' => 8999,
        'status' => $status,
    ]);
}

test('an unpaid invoice is reminded on its due date', function () {
    Notification::fake();

    $invoice = invoiceDue('2026-10-08');

    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertSentOnDemand(
        InvoiceDueNotification::class,
        fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'accounts@company.test'
    );

    expect($invoice->fresh()->due_reminder_sent_at)->not->toBeNull();
});

test('no reminder on any day but the due date', function (string $today) {
    Notification::fake();

    invoiceDue('2026-10-08');

    $this->travelTo("{$today} 08:30");
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertNothingSent();
})->with([
    'the day before' => '2026-10-07',
    'the day after' => '2026-10-09',
    'a week later' => '2026-10-15',
]);

test('running twice sends only one reminder', function () {
    Notification::fake();

    invoiceDue('2026-10-08');

    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertCount(1);
});

test('a paid invoice is not chased', function () {
    Notification::fake();

    invoiceDue('2026-10-08', Invoice::STATUS_PAID);

    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertNothingSent();
});

test('a draft is not chased', function () {
    Notification::fake();

    invoiceDue('2026-10-08', Invoice::STATUS_DRAFT);

    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertNothingSent();
});

test('a failed send is left unstamped, so the next run retries it', function () {
    $invoice = invoiceDue('2026-10-08');

    // Nothing listening on this port.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders')->assertFailed();
    $this->travelBack();

    expect($invoice->fresh()->due_reminder_sent_at)->toBeNull();
});

test('a missed run can be caught up with --date', function () {
    Notification::fake();

    invoiceDue('2026-10-08');

    $this->travelTo('2026-10-11 09:00');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    $this->artisan('invoices:send-due-reminders --date=2026-10-08')->assertSuccessful();
    $this->travelBack();

    Notification::assertCount(1);
});

test('a dry run sends nothing and stamps nothing', function () {
    Notification::fake();

    $invoice = invoiceDue('2026-10-08');

    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders --dry-run')
        ->expectsOutputToContain('would remind')
        ->assertSuccessful();
    $this->travelBack();

    Notification::assertNothingSent();
    expect($invoice->fresh()->due_reminder_sent_at)->toBeNull();
});

test('the reminder says it is due today, names the amount, and carries the PDF', function () {
    $invoice = invoiceDue('2026-10-08');

    $mail = (new InvoiceDueNotification($invoice))->toMail(new AnonymousNotifiable);
    $body = implode("\n", array_merge($mail->introLines, $mail->outroLines));

    expect($mail->subject)->toBe("Payment due today — invoice {$invoice->number}")
        ->and($body)->toContain('is due today')
        ->and($body)->toContain('₱8,999.00')
        // Payments are matched by hand, so don't chase someone who has paid.
        ->and($body)->toContain('If you have already paid')
        ->and($mail->rawAttachments[0]['name'])->toBe("{$invoice->number}.pdf")
        ->and($mail->rawAttachments[0]['data'])->toStartWith('%PDF-');
});

test('a renewal reminder says the workspace is paused', function () {
    // Due date is period end + 7, so by now the period has run out.
    $invoice = invoiceDue('2026-10-08', periodEnd: '2026-10-01');

    $this->travelTo('2026-10-08 08:30');
    $mail = (new InvoiceDueNotification($invoice))->toMail(new AnonymousNotifiable);
    $this->travelBack();

    expect(implode("\n", $mail->introLines))->toContain('workspace is paused');
});

test('a first invoice carries no paused line, since nothing has lapsed', function () {
    // No period_end — the trial conversion, due the day it was raised.
    $invoice = invoiceDue('2026-10-08');

    $mail = (new InvoiceDueNotification($invoice))->toMail(new AnonymousNotifiable);

    expect(implode("\n", $mail->introLines))->not->toContain('paused');
});

test('the reminder is copied to the configured address too', function () {
    config(['invoice.cc' => 'accounting@artemis.test']);

    $invoice = invoiceDue('2026-10-08');

    $mail = (new InvoiceDueNotification($invoice))->toMail(new AnonymousNotifiable);

    expect(array_column($mail->cc, 0))->toBe(['accounting@artemis.test']);
});
