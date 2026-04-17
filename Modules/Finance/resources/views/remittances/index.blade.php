<x-finance::layouts.master>
    <div style="max-width:1100px;margin:2rem auto;font-family:sans-serif;">
        <h1>Remittances</h1>
        @if (session('status'))
            <p style="color:green;">{{ session('status') }}</p>
        @endif

        @if ($unreconciledCount > 0)
            <p style="background:#fff3cd;padding:.5rem 1rem;border:1px solid #ffeeba;">
                ⚠ {{ $unreconciledCount }} remittance(s) are not yet linked to a transaction.
                <a href="{{ route('finance.remittances.index', ['unreconciled' => 1]) }}">View unreconciled</a>
            </p>
        @endif

        <p>
            <a href="{{ route('finance.remittances.create') }}">+ New Remittance</a> |
            <a href="{{ route('finance.remittances.index') }}">All</a> |
            <a href="{{ route('finance.remittances.index', ['unreconciled' => 1]) }}">Unreconciled only</a>
        </p>

        <table width="100%" cellpadding="6" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f4f4f4;text-align:left;">
                    <th>Date</th><th>Courier</th><th>Ref</th>
                    <th style="text-align:right;">Gross</th>
                    <th style="text-align:right;">Deductions</th>
                    <th style="text-align:right;">Net</th>
                    <th>Status</th><th>Linked</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($remittances as $r)
                    <tr style="border-top:1px solid #eee;">
                        <td>{{ $r->date->toDateString() }}</td>
                        <td>{{ $r->courier }}</td>
                        <td>{{ $r->reference_no }}</td>
                        <td style="text-align:right;">{{ number_format((float) $r->gross_amount, 2) }}</td>
                        <td style="text-align:right;">{{ number_format((float) $r->deductions, 2) }}</td>
                        <td style="text-align:right;">{{ number_format((float) $r->net_amount, 2) }}</td>
                        <td>{{ $r->status }}</td>
                        <td>
                            @if ($r->transaction)
                                ✓
                            @else
                                <span style="color:#b00;">unreconciled</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('finance.remittances.show', $r) }}">view</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:1rem;">{{ $remittances->links() }}</div>
    </div>
</x-finance::layouts.master>
