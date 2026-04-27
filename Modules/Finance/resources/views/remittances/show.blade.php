<x-finance::layouts.master>
    <div style="max-width:800px;margin:2rem auto;font-family:sans-serif;">
        <p><a href="{{ route('finance.remittances.index') }}">&larr; Remittances</a></p>

        <h1>Remittance #{{ $remittance->id }}</h1>

        @if (! $remittance->transaction)
            <p style="background:#fff3cd;padding:.5rem 1rem;border:1px solid #ffeeba;">
                ⚠ This remittance is not linked to any transaction (unreconciled).
                <a href="{{ route('finance.transactions.create', ['category' => 'remittance', 'remittance_id' => $remittance->id]) }}">
                    Record deposit transaction
                </a>
            </p>
        @endif

        <dl>
            <dt>Courier</dt><dd>{{ $remittance->courier }}</dd>
            <dt>Date</dt><dd>{{ $remittance->date->toDateString() }}</dd>
            <dt>Reference</dt><dd>{{ $remittance->reference_no }}</dd>
            <dt>Gross</dt><dd>{{ number_format((float) $remittance->gross_amount, 2) }}</dd>
            <dt>Deductions</dt><dd>{{ number_format((float) $remittance->deductions, 2) }}</dd>
            <dt>Net</dt><dd><strong>{{ number_format((float) $remittance->net_amount, 2) }}</strong></dd>
            <dt>Status</dt><dd>{{ $remittance->status }}</dd>
            <dt>Notes</dt><dd>{{ $remittance->notes }}</dd>
            <dt>Linked Transaction</dt>
            <dd>
                @if ($remittance->transaction)
                    {{ $remittance->transaction->date->toDateString() }} &middot;
                    {{ $remittance->transaction->account?->name }} &middot;
                    {{ number_format((float) $remittance->transaction->amount, 2) }}
                    <a href="{{ route('finance.transactions.edit', $remittance->transaction) }}">edit</a>
                @else
                    <em>None</em>
                @endif
            </dd>
        </dl>

        <p><a href="{{ route('finance.remittances.edit', $remittance) }}">Edit</a></p>
    </div>
</x-finance::layouts.master>
