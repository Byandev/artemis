<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminder that a subscription invoice is coming due. Sent on creation when the
 * invoice already falls due today, and by `invoices:send-due-reminders` for the
 * ahead-of-time offsets. Subject and body are generated, not admin-authored.
 */
class InvoiceDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public int $daysUntilDue = 0,
    ) {}

    public function envelope(): Envelope
    {
        $sellerEmail = config('invoice.seller.email');

        return new Envelope(
            subject: "Subscription Notice — Invoice {$this->invoice->number} {$this->duePhrase()}",
            // The copy asks the client to reply; without this that reply goes
            // to the raw MAIL_FROM address rather than the billing inbox.
            replyTo: $sellerEmail
                ? [new Address($sellerEmail, config('invoice.seller.name') ?: config('app.name'))]
                : [],
        );
    }

    /** "is due today" / "is due tomorrow" / "is due in 3 days". */
    public function duePhrase(): string
    {
        return match (true) {
            $this->daysUntilDue <= 0 => 'is due today',
            $this->daysUntilDue === 1 => 'is due tomorrow',
            default => "is due in {$this->daysUntilDue} days",
        };
    }

    public function content(): Content
    {
        // Minimal HTML — no layout or styling beyond centring the copy, which
        // a text/plain part can't do. The text part ships alongside it: an
        // HTML-only message scores worse with spam filters and reads badly in
        // text-only clients.
        return new Content(
            view: 'emails.invoices.due-reminder',
            text: 'emails.invoices.due-reminder-text',
            with: [
                'invoice' => $this->invoice,
                'duePhrase' => $this->duePhrase(),
                'daysUntilDue' => $this->daysUntilDue,
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
