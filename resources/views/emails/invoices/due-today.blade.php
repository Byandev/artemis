<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number }} is due today</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif; color:#27272a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:10px; overflow:hidden;">

                    <tr>
                        <td style="background:#0d8264; padding:24px 32px;">
                            <div style="font-size:20px; font-weight:bold; color:#ffffff;">
                                {{ $seller['name'] ?? config('app.name') }}
                            </div>
                            <div style="font-size:12px; color:#bfe6da; margin-top:2px;">
                                Payment reminder
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px; font-size:15px;">
                                Hi{{ $invoice->bill_to_name ? ' ' . $invoice->bill_to_name : '' }},
                            </p>

                            <p style="margin:0 0 20px; font-size:14px; line-height:1.6;">
                                This is a reminder that your subscription
                                invoice <strong>{{ $invoice->number }}</strong>
                                is due
                                <strong>today, {{ $invoice->due_date?->format('F j, Y') }}</strong>.
                                The full invoice is attached as a PDF.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e4e7; border-radius:8px; margin-bottom:20px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; color:#71717a;">Invoice</td>
                                    <td style="padding:12px 16px; font-size:13px; text-align:right; font-weight:bold;">{{ $invoice->number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; color:#71717a; border-top:1px solid #e4e4e7;">Issue date</td>
                                    <td style="padding:12px 16px; font-size:13px; text-align:right; border-top:1px solid #e4e4e7;">{{ $invoice->issue_date?->format('M j, Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; color:#71717a; border-top:1px solid #e4e4e7;">Due date</td>
                                    <td style="padding:12px 16px; font-size:13px; text-align:right; border-top:1px solid #e4e4e7;">{{ $invoice->due_date?->format('M j, Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; font-size:14px; font-weight:bold; border-top:1px solid #e4e4e7; background:#fafafa;">Amount due</td>
                                    <td style="padding:14px 16px; font-size:16px; font-weight:bold; text-align:right; border-top:1px solid #e4e4e7; background:#fafafa; color:#0d8264;">
                                        {{ $symbol }}{{ number_format((float) $invoice->total, 2) }}
                                    </td>
                                </tr>
                            </table>

                            @if ($paymentInstructions)
                                <div style="font-size:13px; color:#3f3f46; line-height:1.6; white-space:pre-line; background:#fafafa; border:1px solid #e4e4e7; border-radius:8px; padding:16px;">{{ $paymentInstructions }}</div>
                            @endif

                            <p style="margin:20px 0 0; font-size:13px; color:#71717a; line-height:1.6;">
                                If you have already settled this invoice, please disregard this message.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px; background:#fafafa; border-top:1px solid #e4e4e7; font-size:12px; color:#a1a1aa;">
                            {{ $seller['name'] ?? config('app.name') }}
                            @if (! empty($seller['email']))
                                &middot; {{ $seller['email'] }}
                            @endif
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
