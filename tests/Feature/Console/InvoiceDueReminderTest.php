<?php

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceDueNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Three chases as an unpaid invoice comes due — five days out, three days out,
 * and the due date itself — each firing once and never again.
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

test('a reminder goes out at each configured stage', function (string $today, int $offset) {
    Notification::fake();

    $invoice = invoiceDue('2026-10-08');

    $this->travelTo("{$today} 08:30");
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertSentOnDemand(
        InvoiceDueNotification::class,
        fn ($n) => $n->daysUntilDue === $offset
    );

    expect($invoice->fresh()->reminders_sent)->toBe([$offset]);
})->with([
    'five days out' => ['2026-10-03', 5],
    'three days out' => ['2026-10-05', 3],
    'the due date' => ['2026-10-08', 0],
]);

test('all three stages fire across the run-up, in order', function () {
    Notification::fake();

    $invoice = invoiceDue('2026-10-08');

    // Every morning from a fortnight out to the due date.
    foreach (range(0, 14) as $daysAgo) {
        $this->travelTo(Carbon\Carbon::parse('2026-09-24')->addDays($daysAgo)->setTime(8, 30));
        $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    }
    $this->travelBack();

    // Three chases across fifteen mornings, not fifteen.
    Notification::assertCount(3);
    expect($invoice->fresh()->reminders_sent)->toBe([5, 3, 0]);
});

test('no reminder on a day that is not a configured stage', function (string $today) {
    Notification::fake();

    invoiceDue('2026-10-08');

    $this->travelTo("{$today} 08:30");
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertNothingSent();
})->with([
    'six days out' => '2026-10-02',
    'four days out' => '2026-10-04',
    'two days out' => '2026-10-06',
    'the day after' => '2026-10-09',
]);

test('running twice on the same stage sends only one reminder', function () {
    Notification::fake();

    invoiceDue('2026-10-08');

    $this->travelTo('2026-10-05 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertCount(1);
});

test('paying partway through stops the rest of the chases', function () {
    Notification::fake();

    $invoice = invoiceDue('2026-10-08');

    $this->travelTo('2026-10-03 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();

    $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);

    $this->travelTo('2026-10-05 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    // Only the five-day chase went out, before they paid.
    Notification::assertCount(1);
});

test('a draft is never chased', function () {
    Notification::fake();

    invoiceDue('2026-10-08', Invoice::STATUS_DRAFT);

    $this->travelTo('2026-10-05 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertNothingSent();
});

test('a failed send is not recorded, so the next run retries that stage', function () {
    $invoice = invoiceDue('2026-10-08');

    // Nothing listening on this port.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

    $this->travelTo('2026-10-05 08:30');
    $this->artisan('invoices:send-due-reminders')->assertFailed();
    $this->travelBack();

    expect($invoice->fresh()->reminders_sent)->toBeNull();
});

test('a missed stage can be caught up with --date', function () {
    Notification::fake();

    $invoice = invoiceDue('2026-10-08');

    // The three-day run never happened; it is now the due date.
    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->artisan('invoices:send-due-reminders --date=2026-10-05')->assertSuccessful();
    $this->travelBack();

    Notification::assertCount(2);
    expect($invoice->fresh()->reminders_sent)->toBe([0, 3]);
});

test('the stages can be reconfigured', function () {
    Notification::fake();

    config(['invoice.reminder_days' => [1]]);

    invoiceDue('2026-10-08');

    $this->travelTo('2026-10-05 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travelTo('2026-10-07 08:30');
    $this->artisan('invoices:send-due-reminders')->assertSuccessful();
    $this->travelBack();

    Notification::assertCount(1);
});

test('reminders can be switched off entirely', function () {
    Notification::fake();

    config(['invoice.reminder_days' => []]);

    invoiceDue('2026-10-08');

    $this->travelTo('2026-10-08 08:30');
    $this->artisan('invoices:send-due-reminders')
        ->expectsOutputToContain('No reminder days configured')
        ->assertSuccessful();
    $this->travelBack();

    Notification::assertNothingSent();
});

test('a dry run sends nothing and records nothing', function () {
    Notification::fake();

    $invoice = invoiceDue('2026-10-08');

    $this->travelTo('2026-10-03 08:30');
    $this->artisan('invoices:send-due-reminders --dry-run')
        ->expectsOutputToContain('5 days out')
        ->assertSuccessful();
    $this->travelBack();

    Notification::assertNothingSent();
    expect($invoice->fresh()->reminders_sent)->toBeNull();
});

test('each stage words the deadline differently', function (int $offset, string $when) {
    $invoice = invoiceDue('2026-10-08');

    $mail = (new InvoiceDueNotification($invoice, $offset))->toMail(new AnonymousNotifiable);
    $body = implode("\n", $mail->introLines);

    expect($mail->subject)->toBe("Payment due {$when} — invoice {$invoice->number}")
        ->and($body)->toContain("is due {$when}");

    // An early chase names the date too, so nobody has to count days.
    $offset > 0
        ? expect($body)->toContain('October 8, 2026')
        : expect($body)->not->toContain('October 8, 2026');
})->with([
    'five days out' => [5, 'in 5 days'],
    'three days out' => [3, 'in 3 days'],
    'tomorrow' => [1, 'tomorrow'],
    'the due date' => [0, 'today'],
]);

test('the reminder names the amount and carries the PDF', function () {
    $invoice = invoiceDue('2026-10-08');

    $mail = (new InvoiceDueNotification($invoice, 3))->toMail(new AnonymousNotifiable);
    $body = implode("\n", array_merge($mail->introLines, $mail->outroLines));

    expect($body)->toContain('₱8,999.00')
        // Payments are matched by hand, so don't chase someone who has paid.
        ->and($body)->toContain('If you have already paid')
        ->and($mail->rawAttachments[0]['name'])->toBe("{$invoice->number}.pdf")
        ->and($mail->rawAttachments[0]['data'])->toStartWith('%PDF-');
});

test('a renewal chase says the workspace is paused once the period has gone', function () {
    $invoice = invoiceDue('2026-10-08', periodEnd: '2026-10-01');

    // Three days out is Oct 5 — the period ran out on the 1st.
    $this->travelTo('2026-10-05 08:30');
    $mail = (new InvoiceDueNotification($invoice, 3))->toMail(new AnonymousNotifiable);
    $this->travelBack();

    expect(implode("\n", $mail->introLines))->toContain('workspace is paused');
});

test('a first invoice carries no paused line, since nothing has lapsed', function () {
    $invoice = invoiceDue('2026-10-08');

    $mail = (new InvoiceDueNotification($invoice, 5))->toMail(new AnonymousNotifiable);

    expect(implode("\n", $mail->introLines))->not->toContain('paused');
});

test('the reminder is copied to the configured address too', function () {
    config(['invoice.cc' => 'accounting@artemis.test']);

    $invoice = invoiceDue('2026-10-08');

    $mail = (new InvoiceDueNotification($invoice, 5))->toMail(new AnonymousNotifiable);

    expect(array_column($mail->cc, 0))->toBe(['accounting@artemis.test']);
});
