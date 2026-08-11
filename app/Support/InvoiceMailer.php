<?php

namespace App\Support;

use App\Models\Invoice;
use App\Notifications\InvoiceDueNotification;
use App\Notifications\InvoiceIssuedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Emailing an invoice to whoever pays for the workspace.
 *
 * Deliberately never throws. An invoice that exists but wasn't emailed can
 * still be put right — flip it to draft and back to sent, or download the PDF
 * and send it by hand. An invoice rolled back because the mail server was down
 * is money quietly not billed.
 */
class InvoiceMailer
{
    /**
     * Send the invoice, returning the address it went to, or null if it wasn't
     * sent — no recipient, still a draft, or the send failed.
     */
    public static function send(Invoice $invoice): ?string
    {
        return static::deliver($invoice, new InvoiceIssuedNotification($invoice));
    }

    /** Remind the payer that the invoice falls due today. */
    public static function remind(Invoice $invoice): ?string
    {
        return static::deliver($invoice, new InvoiceDueNotification($invoice));
    }

    /**
     * The one path every invoice email takes: same recipient rules, same
     * refusal to throw, whatever is being sent.
     */
    private static function deliver(Invoice $invoice, Notification $notification): ?string
    {
        // A draft is not an invoice yet. Nothing goes out until it's issued.
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            return null;
        }

        $recipient = static::recipientFor($invoice);

        if (! $recipient) {
            Log::warning('Invoice email had nowhere to go.', [
                'invoice' => $invoice->number,
                'workspace_id' => $invoice->workspace_id,
            ]);

            return null;
        }

        try {
            NotificationFacade::route('mail', $recipient)->notify($notification);

            return $recipient;
        } catch (Throwable $e) {
            Log::error('Could not email invoice.', [
                'invoice' => $invoice->number,
                'recipient' => $recipient,
                'notification' => class_basename($notification),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Whoever the invoice says it is billed to wins — an admin who typed an
     * address onto a one-off invoice meant that one. Only when the invoice
     * names nobody does the workspace answer for it, which is billing_email
     * first and the owner after.
     */
    public static function recipientFor(Invoice $invoice): ?string
    {
        $invoice->loadMissing('workspace.owner');

        $email = $invoice->bill_to_email ?: $invoice->workspace?->billingEmail();

        return filter_var((string) $email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
