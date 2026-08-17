Hi,

A reminder that the subscription for {{ $workspaceName }} {{ $duePhrase }}@if ($dueDate), on {{ $dueDate->format('F j, Y') }}@endif.
@if ($planName)

Plan: {{ $planName }}@if ($planPrice !== null) — {{ $symbol }}{{ number_format((float) $planPrice, 2) }}@endif
@endif

@if ($daysUntilDue > 0)
Renewing on or before the due date keeps your workspace active - no action is needed beyond payment.
@else
This is the last day of the current period. Renew today to keep access uninterrupted.
@endif
@if ($paymentInstructions)

{{ $paymentInstructions }}
@endif

If you have already paid, reply and let us know - we will check and update our records.

Regards,
{{ config('app.name') }}
