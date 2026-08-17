{{-- Centred container, copy left-aligned inside. The outer table is what
     actually centres it — `margin:0 auto` on a div is unreliable in Outlook. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
                <tr>
                    <td style="text-align:left; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.65; color:#000000;">

                        <p style="margin:0 0 20px;">
                            Hi,<br>
                            A reminder that the subscription for <strong>{{ $workspaceName }}</strong> <strong>{{ $duePhrase }}</strong>@if ($dueDate), on <strong>{{ $dueDate->format('F j, Y') }}</strong>@endif.
                        </p>

                        @if ($planName)
                            <p style="margin:0 0 20px; font-size:22px; line-height:1.3; font-weight:bold;">
                                {{ $planName }}@if ($planPrice !== null) — {{ $symbol }}{{ number_format((float) $planPrice, 2) }}@endif
                            </p>
                        @endif

                        @if ($daysUntilDue > 0)
                            <p style="margin:0 0 20px;">Renewing on or before the due date keeps your workspace active &mdash; no action is needed beyond payment.</p>
                        @else
                            <p style="margin:0 0 20px;"><strong>This is the last day of the current period.</strong> Renew today to keep access uninterrupted.</p>
                        @endif

                        @if ($paymentInstructions)
                            <p style="margin:0 0 20px; font-size:15px; line-height:1.7; white-space:pre-line;">{{ $paymentInstructions }}</p>
                        @endif

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
