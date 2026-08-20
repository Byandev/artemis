{{-- Centred container, copy left-aligned inside. The outer table is what
     actually centres it — `margin:0 auto` on a div is unreliable in Outlook. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
                <tr>
                    <td style="text-align:left; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.65; color:#000000;">

                        <p style="margin:0 0 20px;">
                            Hi{{ $invoice->bill_to_name ? ' '.$invoice->bill_to_name : '' }},<br>
                            A reminder that invoice <strong>{{ $invoice->number }}</strong>@if ($invoice->workspace) for <strong>{{ $invoice->workspace->name }}</strong>@endif <strong>{{ $duePhrase }}</strong>, on <strong>{{ $invoice->due_date?->format('F j, Y') }}</strong>.
                        </p>

                        {{-- The one number the reader is looking for. --}}
                        <p style="margin:0 0 20px; font-size:22px; line-height:1.3; font-weight:bold;">
                            Amount due: {{ $symbol }}{{ number_format((float) $invoice->total, 2) }}
                        </p>

                        @if ($daysUntilDue > 0)
                            <p style="margin:0 0 20px;">Settling on or before the due date keeps your workspace active &mdash; no action is needed beyond payment.</p>
                        @else
                            <p style="margin:0 0 20px;"><strong>Your workspace is paused until this is settled.</strong> Access comes back as soon as the payment is in.</p>
                        @endif

                        @if ($paymentInstructions)
                            <p style="margin:0 0 20px; font-size:15px; line-height:1.7; white-space:pre-line;">{{ $paymentInstructions }}</p>
                        @endif

                        <p style="margin:0 0 20px;">The full invoice is attached to this email as a PDF.</p>

                        <p style="margin:0 0 20px;">If you have already paid, reply and let us know &mdash; we will check and update our records.</p>

                        <p style="margin:0; font-size:15px;">
                            Regards,<br>
                            {{ config('app.name') }}
                        </p>

                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
