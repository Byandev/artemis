<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminder that an invoice falls due today. Sent by the
 * `invoices:notify-due-today` command, once per invoice per day.
 */
class InvoiceDueTodayMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        // Generated, not admin-authored — the invoice number keeps it specific
        // enough for the client to match it against the attached PDF.
        return new Envelope(
            subject: "Subscription Notice — Invoice {$this->invoice->number} is due today",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.due-today',
            with: [
                'invoice' => $this->invoice,
                'seller' => config('invoice.seller'),
                'symbol' => config('invoice.currency_symbol'),
                'paymentInstructions' => config('invoice.payment_instructions'),
            ],
        );
    }

    /**
     * The invoice itself, as a PDF. Built lazily so the render cost is only
     * paid when the message is actually delivered.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => InvoicePdf::output($this->invoice),
                InvoicePdf::filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }
}
