<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\CopiesInvoiceEmail;
use App\Support\InvoicePdf;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A reminder that an unpaid invoice is coming due, or is due today.
 *
 * Kept apart from InvoiceIssuedNotification rather than folded into it: this
 * one arrives at a customer who has already had the bill and not paid, and
 * nothing about that reads the same as sending it the first time.
 */
class InvoiceDueNotification extends Notification
{
    use CopiesInvoiceEmail;

    /**
     * @param  int  $daysUntilDue  How far off the due date is. 0 is today.
     */
    public function __construct(public Invoice $invoice, public int $daysUntilDue = 0) {}

    /** "today", "tomorrow", "in 3 days" — how the deadline is described. */
    private function whenDue(): string
    {
        return match ($this->daysUntilDue) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$this->daysUntilDue} days",
        };
    }

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

        $when = $this->whenDue();

        $message = (new MailMessage)
            ->subject("Payment due {$when} — invoice {$this->invoice->number}")
            ->greeting('Hi '.$this->invoice->bill_to_name.',');

        if ($copies = $this->copies($notifiable)) {
            $message->cc($copies);
        }

        $message
            ->line(sprintf(
                'A reminder that invoice %s%s is due %s%s.',
                $this->invoice->number,
                $workspace ? " for {$workspace}" : '',
                $when,
                // Name the date as well when it isn't today — "in 5 days"
                // alone makes the reader do arithmetic.
                $this->daysUntilDue > 0 && $this->invoice->due_date
                    ? ', on '.$this->invoice->due_date->format('F j, Y')
                    : ''
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
