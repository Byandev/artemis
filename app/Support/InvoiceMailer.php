<?php

namespace App\Support;

use App\Models\Invoice;
use App\Notifications\InvoiceIssuedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Emailing an issued invoice to whoever pays for the workspace.
 *
 * Deliberately never throws. An invoice that exists but wasn't emailed is a
 * nuisance an admin can fix by resending; an invoice rolled back because the
 * mail server was down is money quietly not billed.
 */
class InvoiceMailer
{
    /**
     * Send the invoice, returning the address it went to, or null if it wasn't
     * sent — no recipient, still a draft, or the send failed.
     */
    public static function send(Invoice $invoice): ?string
    {
        // A draft is not an invoice yet. Nothing goes out until it's issued.
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            return null;
        }

        $recipient = static::recipientFor($invoice);

        if (! $recipient) {
            Log::warning('Invoice raised with nowhere to send it.', [
                'invoice' => $invoice->number,
                'workspace_id' => $invoice->workspace_id,
            ]);

            return null;
        }

        try {
            Notification::route('mail', $recipient)
                ->notify(new InvoiceIssuedNotification($invoice));

            return $recipient;
        } catch (Throwable $e) {
            Log::error('Could not email invoice.', [
                'invoice' => $invoice->number,
                'recipient' => $recipient,
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
