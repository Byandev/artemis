<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #27272a;
        }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #71717a; }
        .right { text-align: right; }
        .nowrap { white-space: nowrap; }
        .pre { white-space: pre-line; }

        /* ---- Header band ---- */
        .header {
            background: #0d8264;
            color: #ffffff;
            padding: 36px 48px 30px;
        }
        .header td { vertical-align: top; }
        .brand {
            font-size: 25px;
            font-weight: bold;
            letter-spacing: 0.3px;
            color: #ffffff;
        }
        .brand-sub { font-size: 11px; color: #bfe6da; margin-top: 3px; }
        .logo { max-height: 54px; max-width: 220px; }
        .doc-word {
            font-size: 25px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #ffffff;
        }
        .doc-number { font-size: 12px; color: #bfe6da; margin-top: 4px; }
        .accent { height: 4px; background: #10d3a1; font-size: 0; line-height: 0; }

        /* ---- Body ---- */
        .body { padding: 34px 48px 0; }

        .meta td { vertical-align: top; padding-bottom: 4px; }
        .label {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #9ca3af;
            font-weight: bold;
        }
        .party-name { font-weight: bold; font-size: 13.5px; color: #18181b; margin-top: 4px; }
        .meta-row td { padding: 2px 0; font-size: 11.5px; }
        .meta-key { color: #71717a; padding-right: 16px; }
        .meta-val { color: #27272a; font-weight: bold; text-align: right; }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .badge-paid  { background: #d1fae5; color: #065f46; }
        .badge-sent  { background: #dbeafe; color: #1e40af; }
        .badge-draft { background: #f4f4f5; color: #52525b; }

        /* ---- Items ---- */
        .items { margin-top: 30px; }
        .items thead th {
            background: #ecf3f1;
            color: #0c644d;
            text-align: left;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            padding: 10px 12px;
        }
        .items thead th.num { text-align: right; }
        .items tbody td {
            padding: 11px 12px;
            border-bottom: 1px solid #eeeeef;
            vertical-align: top;
        }
        .items tbody td.num { text-align: right; white-space: nowrap; }
        .items .desc { color: #18181b; }

        /* ---- Totals ---- */
        .totals { margin-top: 18px; }
        .totals .trow td { padding: 6px 12px; font-size: 12px; }
        .totals .tkey { text-align: right; color: #71717a; }
        .totals .tval { text-align: right; white-space: nowrap; width: 140px; color: #27272a; }
        .grand td {
            background: #0d8264;
            color: #ffffff;
            font-weight: bold;
            font-size: 14px;
            padding: 12px 14px;
        }
        .grand .tkey { color: #ffffff; text-align: right; }
        .grand .tval { color: #ffffff; }

        /* ---- Panels ---- */
        .panel {
            margin-top: 30px;
            background: #f6f9f8;
            border: 1px solid #d7eae5;
            border-left: 3px solid #0eaa82;
            padding: 16px 20px;
        }
        .panel .label { color: #0c644d; }
        .panel-body { margin-top: 6px; color: #3f3f46; }

        .notes { margin-top: 24px; }
        .notes-body { margin-top: 6px; color: #3f3f46; }

        /* ---- Footer ---- */
        .footer {
            margin-top: 40px;
            padding: 18px 48px;
            border-top: 1px solid #eeeeef;
            text-align: center;
            color: #a1a1aa;
            font-size: 10px;
        }
        .footer strong { color: #0d8264; }
    </style>
</head>
<body>
@php
    $fmt = fn ($n) => $symbol . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $badgeClass = 'badge-' . ($invoice->status === 'paid' ? 'paid' : ($invoice->status === 'sent' ? 'sent' : 'draft'));
    $hasContact = ! empty($seller['tin']) || ! empty($seller['phone']);
@endphp

{{-- ============ HEADER ============ --}}
<div class="header">
    <table>
        <tr>
            <td style="width: 58%;">
                @if (! empty($seller['logo_path']) && file_exists($seller['logo_path']))
                    <img class="logo" src="{{ $seller['logo_path'] }}" alt="{{ $seller['name'] }}">
                @else
                    <div class="brand">{{ $seller['name'] }}</div>
                @endif
                <div class="brand-sub">
                    {{ $seller['email'] }}@if (! empty($seller['phone'])) &nbsp;•&nbsp; {{ $seller['phone'] }}@endif
                </div>
                @if (! empty($seller['tin']))
                    <div class="brand-sub">TIN: {{ $seller['tin'] }}</div>
                @endif
                @if (! empty($seller['address']))
                    <div class="brand-sub pre">{{ $seller['address'] }}</div>
                @endif
            </td>
            <td class="right" style="width: 42%;">
                <div class="doc-word">INVOICE</div>
                <div class="doc-number">{{ $invoice->number }}</div>
            </td>
        </tr>
    </table>
</div>
<div class="accent">&nbsp;</div>

{{-- ============ BODY ============ --}}
<div class="body">

    {{-- Bill-to + meta --}}
    <table class="meta">
        <tr>
            <td style="width: 55%; padding-right: 24px;">
                <div class="label">Billed to</div>
                <div class="party-name">{{ $invoice->bill_to_name }}</div>
                @if ($invoice->bill_to_email)
                    <div class="muted">{{ $invoice->bill_to_email }}</div>
                @endif
                @if ($invoice->bill_to_address)
                    <div class="muted pre">{{ $invoice->bill_to_address }}</div>
                @endif
                @if ($invoice->workspace)
                    <div style="margin-top: 10px;">
                        <span class="label">Workspace</span>
                        <span style="margin-left: 6px; color: #3f3f46;">{{ $invoice->workspace->name }}</span>
                    </div>
                @endif
            </td>
            <td style="width: 45%;">
                <table>
                    <tr class="meta-row">
                        <td class="meta-key">Issue date</td>
                        <td class="meta-val">{{ $invoice->issue_date->format('M j, Y') }}</td>
                    </tr>
                    @if ($invoice->due_date)
                        <tr class="meta-row">
                            <td class="meta-key">Due date</td>
                            <td class="meta-val">{{ $invoice->due_date->format('M j, Y') }}</td>
                        </tr>
                    @endif
                    <tr class="meta-row">
                        <td class="meta-key" style="padding-top: 8px;">Status</td>
                        <td class="right" style="padding-top: 8px;">
                            <span class="badge {{ $badgeClass }}">{{ $invoice->status }}</span>
                        </td>
                    </tr>
                    <tr class="meta-row">
                        <td class="meta-key" style="padding-top: 8px;">Amount due</td>
                        <td class="meta-val" style="padding-top: 8px; font-size: 13px; color: #0d8264;">
                            {{ $fmt($invoice->total) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 50%;">Description</th>
                <th class="num" style="width: 12%;">Qty</th>
                <th class="num" style="width: 19%;">Unit price</th>
                <th class="num" style="width: 19%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->line_items as $item)
                <tr>
                    <td class="desc">{{ $item['description'] }}</td>
                    <td class="num">{{ $qty($item['quantity']) }}</td>
                    <td class="num">{{ $fmt($item['unit_price']) }}</td>
                    <td class="num">{{ $fmt($item['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        <tr>
            <td style="width: 58%;"></td>
            <td style="width: 42%;">
                <table>
                    <tr class="trow">
                        <td class="tkey">Subtotal</td>
                        <td class="tval">{{ $fmt($invoice->subtotal) }}</td>
                    </tr>
                    @if ((float) $invoice->tax_rate > 0)
                        <tr class="trow">
                            <td class="tkey">Tax ({{ $qty($invoice->tax_rate) }}%)</td>
                            <td class="tval">{{ $fmt($invoice->tax_amount) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td class="tkey">Total due ({{ $invoice->currency }})</td>
                        <td class="tval">{{ $fmt($invoice->total) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Notes --}}
    @if ($invoice->notes)
        <div class="notes">
            <div class="label">Notes</div>
            <div class="notes-body pre">{{ $invoice->notes }}</div>
        </div>
    @endif

    {{-- Payment details --}}
    @if ($paymentInstructions)
        <div class="panel">
            <div class="label">Payment details</div>
            <div class="panel-body pre">{{ $paymentInstructions }}</div>
        </div>
    @endif

</div>

{{-- ============ FOOTER ============ --}}
<div class="footer">
    Thank you for your business. &nbsp;•&nbsp; <strong>{{ $seller['name'] }}</strong> &nbsp;•&nbsp; {{ $seller['email'] }}
</div>

</body>
</html>
