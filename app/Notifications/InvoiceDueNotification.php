<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\CopiesInvoiceEmail;
use App\Support\InvoicePdf;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The reminder that goes out on an invoice's due date.
 *
 * Kept apart from InvoiceIssuedNotification rather than folded into it: this
 * one arrives at a customer who has already had the bill and not paid, and
 * nothing about that reads the same as sending it the first time.
 */
class InvoiceDueNotification extends Notification
{
    use CopiesInvoiceEmail;

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
        $workspace = $this->invoice->workspace?->name;

        $message = (new MailMessage)
            ->subject("Payment due today — invoice {$this->invoice->number}")
            ->greeting('Hi '.$this->invoice->bill_to_name.',');

        if ($copies = $this->copies($notifiable)) {
            $message->cc($copies);
        }

        $message
            ->line(sprintf(
                'A reminder that invoice %s%s is due today.',
                $this->invoice->number,
                $workspace ? " for {$workspace}" : ''
            ))
            ->line("**Amount due:** {$symbol}{$amount}");

        // By the due date a renewal's period has already run out, so the
        // workspace is shut — worth saying, because it is the thing they will
        // notice and the thing paying will fix.
        if ($this->invoice->period_end?->isPast()) {
            $message->line('Your workspace is paused until this is settled. Access comes back as soon as the payment is in.');
        }

        foreach (preg_split('/\R/', (string) config('invoice.payment_instructions')) as $line) {
            if (trim($line) !== '') {
                $message->line($line);
            }
        }

        return $message
            // Nobody should be chased for money they have already sent, and
            // payments are matched by hand here, so invite the correction.
            ->line('If you have already paid, reply and let us know — we will check and update our records.')
            ->attachData(
                InvoicePdf::render($this->invoice),
                InvoicePdf::filename($this->invoice),
                ['mime' => 'application/pdf'],
            );
    }
}
