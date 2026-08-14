<?php

namespace App\Support;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The invoice PDF, rendered in one place so the copy an admin downloads and the
 * copy attached to an email are always the same document.
 */
class InvoicePdf
{
    /** Raw PDF bytes. */
    public static function render(Invoice $invoice): string
    {
        $invoice->loadMissing('workspace:id,name,slug');

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'seller' => config('invoice.seller'),
            'symbol' => config('invoice.currency_symbol'),
            'paymentInstructions' => config('invoice.payment_instructions'),
        ])->setPaper('a4')->output();
    }

    /** The filename it is offered under, in a download or as an attachment. */
    public static function filename(Invoice $invoice): string
    {
        return "{$invoice->number}.pdf";
    }
}
