Hi{{ $invoice->bill_to_name ? ' '.$invoice->bill_to_name : '' }},

A reminder that invoice {{ $invoice->number }}@if ($invoice->workspace) for {{ $invoice->workspace->name }}@endif {{ $duePhrase }}, on {{ $invoice->due_date?->format('F j, Y') }}.

Amount due: {{ $symbol }}{{ number_format((float) $invoice->total, 2) }}

@if ($daysUntilDue > 0)
Settling on or before the due date keeps your workspace active - no action is needed beyond payment.
@else
Your workspace is paused until this is settled. Access comes back as soon as the payment is in.
@endif
@if ($paymentInstructions)

{{ $paymentInstructions }}
@endif

The full invoice is attached to this email as a PDF.

If you have already paid, reply and let us know - we will check and update our records.

Regards,
{{ config('app.name') }}
