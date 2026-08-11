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

    /**
     * Addresses copied on every invoice, so the business keeps its own record
     * of what went out. Comma separated in config for more than one inbox.
     *
     * A malformed entry is dropped rather than allowed to fail the send — a
     * typo in an internal copy must not stop the customer getting their bill.
     *
     * @return array<int, string>
     */
    private function copies(object $notifiable): array
    {
        $configured = array_map('trim', explode(',', (string) config('invoice.cc')));

        // Not every notifiable answers this — don't assume one that does.
        $to = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail')
            : null;

        $to = is_string($to) ? strtolower($to) : null;

        return array_values(array_filter(
            $configured,
            fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL)
                // Never copy the address it is already addressed to, or the
                // recipient gets the same mail twice over.
                && strtolower($email) !== $to
        ));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->invoice->loadMissing(['workspace:id,name,slug', 'plan:id,name']);

        $symbol = config('invoice.currency_symbol');
        $amount = number_format((float) $this->invoice->total, 2);
        $due = $this->invoice->due_date;
        $workspace = $this->invoice->workspace?->name;
        $plan = $this->invoice->plan?->name;

        /**
         * Only renewals carry a period, and they arrive a week before anything
         * is owed — so they have to answer "why am I getting this now?" up
         * front. A first invoice off the free trial explains itself.
         */
        $renewsOn = $this->invoice->period_end;

        $message = (new MailMessage)
            ->subject($renewsOn
                ? "Renewal invoice {$this->invoice->number}".($plan ? " — {$plan} plan" : '')
                : "Invoice {$this->invoice->number} from ".config('invoice.seller.name'))
            ->greeting('Hi '.$this->invoice->bill_to_name.',');

        if ($copies = $this->copies($notifiable)) {
            $message->cc($copies);
        }

        if ($renewsOn) {
            $message->line(sprintf(
                'Your %s%s renews on %s. The invoice for the coming month is attached as a PDF.',
                $plan ? "{$plan} plan" : 'subscription',
                $workspace ? " for {$workspace}" : '',
                $renewsOn->format('F j, Y')
            ));
        } else {
            // Workspace names often already end in "Workspace", so naming it
            // without appending the word again keeps this readable.
            $message->line($workspace
                ? "Here's your invoice for {$workspace}, attached as a PDF."
                : "Here's your invoice, attached as a PDF.");
        }

        $message->line("**Amount due:** {$symbol}{$amount}");

        if ($renewsOn) {
            // Spell out the month being bought, so the amount is anchored to
            // something rather than arriving on its own.
            $message->line(sprintf(
                '**Covers:** %s – %s',
                $renewsOn->format('M j'),
                $renewsOn->copy()->addMonth()->format('M j, Y')
            ));
        }

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
