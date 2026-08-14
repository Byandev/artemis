<?php

namespace App\Support;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;

/**
 * One definition of the invoice PDF, shared by the admin download endpoint and
 * the due-date email attachment, so the two can't drift apart.
 */
class InvoicePdf
{
    public static function make(Invoice $invoice): PdfInstance
    {
        // The template prints the workspace name, and the mailable is queued —
        // so the relation may not be loaded when this runs on a worker.
        $invoice->loadMissing('workspace:id,name,slug');

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'seller' => config('invoice.seller'),
            'symbol' => config('invoice.currency_symbol'),
            'paymentInstructions' => config('invoice.payment_instructions'),
        ])->setPaper('a4');
    }

    /** Raw PDF bytes, for attaching to a mail message. */
    public static function output(Invoice $invoice): string
    {
        return static::make($invoice)->output();
    }

    public static function filename(Invoice $invoice): string
    {
        return "{$invoice->number}.pdf";
    }
}
