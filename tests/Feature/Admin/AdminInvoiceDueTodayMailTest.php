<?php

use App\Mail\InvoiceDueTodayMail;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\InvoicePdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/** Payload for POST /admin/invoices, due today and issued by default. */
function invoicePayload(Workspace $workspace, array $overrides = []): array
{
    return array_merge([
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Meta Digitrading Enterprise Co',
        'bill_to_email' => 'accounts@example.com',
        'bill_to_address' => 'Magahis, Tuy Batangas',
        'issue_date' => Carbon::today()->toDateString(),
        'due_date' => Carbon::today()->toDateString(),
        'currency' => 'PHP',
        'tax_rate' => 0,
        'line_items' => [
            ['description' => 'Solo plan — subscription', 'quantity' => 1, 'unit_price' => 4499],
        ],
        'status' => 'sent',
    ], $overrides);
}

beforeEach(function () {
    Mail::fake();

    $this->admin = User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create(['email' => 'owner@example.com']);
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
});

it('emails the client when an invoice is created due today', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace))
        ->assertRedirect(route('admin.invoices.index'));

    Mail::assertQueued(InvoiceDueTodayMail::class, function ($mail) {
        return $mail->hasTo('accounts@example.com')
            && $mail->invoice->due_date->isToday();
    });
});

it('does not email when the invoice is not due until later', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace, [
            'due_date' => Carbon::today()->addWeek()->toDateString(),
        ]))
        ->assertRedirect();

    Mail::assertNothingQueued();
});

it('does not email for a draft invoice due today', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace, [
            'status' => 'draft',
        ]))
        ->assertRedirect();

    Mail::assertNothingQueued();
});

it('does not email for an invoice created already paid', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace, [
            'status' => 'paid',
        ]))
        ->assertRedirect();

    Mail::assertNothingQueued();
});

it('falls back to the workspace owner when no billing email is given', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace, [
            'bill_to_email' => null,
        ]))
        ->assertRedirect();

    Mail::assertQueued(InvoiceDueTodayMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
});

it('still creates the invoice regardless of the notice', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $invoice = Invoice::where('workspace_id', $this->workspace->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->total)->toEqual('4499.00')
        ->and($invoice->status)->toBe(Invoice::STATUS_SENT);
});

it('renders the invoice number, amount and due date in the email', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace))
        ->assertRedirect();

    $invoice = Invoice::where('workspace_id', $this->workspace->id)->firstOrFail();
    $rendered = (new InvoiceDueTodayMail($invoice))->render();

    expect($rendered)->toContain($invoice->number)
        ->and($rendered)->toContain('4,499.00')
        ->and($rendered)->toContain(Carbon::today()->format('F j, Y'));
});

it('subjects the email with the invoice number', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace))
        ->assertRedirect();

    $invoice = Invoice::where('workspace_id', $this->workspace->id)->firstOrFail();

    expect((new InvoiceDueTodayMail($invoice))->envelope()->subject)
        ->toBe("Subscription Notice — Invoice {$invoice->number} is due today");
});

it('attaches the invoice pdf to the email', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace))
        ->assertRedirect();

    $invoice = Invoice::where('workspace_id', $this->workspace->id)->firstOrFail();

    $mail = new InvoiceDueTodayMail($invoice);
    $attachments = $mail->attachments();

    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]->as)->toBe("{$invoice->number}.pdf")
        ->and($attachments[0]->mime)->toBe('application/pdf');
});

it('renders a real pdf for the attachment', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace))
        ->assertRedirect();

    $invoice = Invoice::where('workspace_id', $this->workspace->id)->firstOrFail();

    $bytes = InvoicePdf::output($invoice);

    // %PDF- is the magic header every PDF starts with.
    expect($bytes)->toStartWith('%PDF-')
        ->and(strlen($bytes))->toBeGreaterThan(1000);
});

it('serves the same pdf from the admin download endpoint', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.store'), invoicePayload($this->workspace))
        ->assertRedirect();

    $invoice = Invoice::where('workspace_id', $this->workspace->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.download', $invoice))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload("{$invoice->number}.pdf");
});
