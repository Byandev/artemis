<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\InvoicePdf;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An issued invoice, with the PDF attached.
 *
 * Not queued — the send happens while the admin is still on the page, so a
 * refusal from Brevo is something they see rather than something that dies
 * quietly in a queue with the customer none the wiser.
 */
class InvoiceIssuedNotification extends Notification
{
    public function __construct(public Invoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->invoice->loadMissing(['workspace:id,name,slug', 'plan:id,name']);

        $symbol = config('invoice.currency_symbol');
        $amount = number_format((float) $this->invoice->total, 2);
        $due = $this->invoice->due_date;

        $message = (new MailMessage)
            ->subject("Invoice {$this->invoice->number} from ".config('invoice.seller.name'))
            ->greeting('Hi '.$this->invoice->bill_to_name.',')
            // Workspace names often already end in "Workspace", so naming it
            // without appending the word again keeps this readable.
            ->line($this->invoice->workspace
                ? "Here's your invoice for {$this->invoice->workspace->name}, attached as a PDF."
                : "Here's your invoice, attached as a PDF.")
            ->line("**Amount due:** {$symbol}{$amount}");

        if ($due) {
            // Due today reads as urgent in a way a bare date does not, and an
            // upgrade off the free trial is due the same day it is raised.
            $message->line($due->isToday()
                ? '**Due:** today'
                : '**Due:** '.$due->format('F j, Y'));
        }

        // One line() per line. Passing the whole block in at once would let
        // markdown fold it into a single paragraph, running the bank name,
        // account name and number together into something nobody can copy.
        foreach (preg_split('/\R/', (string) config('invoice.payment_instructions')) as $line) {
            if (trim($line) !== '') {
                $message->line($line);
            }
        }

        $message
            ->line('Reply to this email if anything looks wrong and we will sort it out.')
            ->attachData(
                InvoicePdf::render($this->invoice),
                InvoicePdf::filename($this->invoice),
                ['mime' => 'application/pdf'],
            );

        return $message;
    }
}
