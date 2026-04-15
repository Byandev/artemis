<x-finance::layouts.master>
    <div style="max-width:1100px;margin:2rem auto;font-family:sans-serif;">
        <p><a href="{{ route('finance.accounts.index') }}">&larr; Accounts</a></p>

        <h1>{{ $account->name }} <small>({{ $account->currency }})</small></h1>

        @if (session('status'))
            <p style="color:green;">{{ session('status') }}</p>
        @endif

        <p>
            Opening: {{ number_format((float) $account->opening_balance, 2) }} &middot;
            Current: <strong>{{ number_format((float) $account->current_balance, 2) }}</strong> &middot;
            <a href="{{ route('finance.accounts.edit', $account) }}">edit account</a>
        </p>
        <p>
            <a href="{{ route('finance.transactions.create', ['account_id' => $account->id]) }}">+ New Transaction</a>
        </p>

        <table width="100%" cellpadding="6" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f4f4f4;text-align:left;">
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th style="text-align:right;">IN</th>
                    <th style="text-align:right;">OUT</th>
                    <th style="text-align:right;">Balance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $t)
                    <tr style="border-top:1px solid #eee;">
                        <td>{{ $t->date->toDateString() }}</td>
                        <td>
                            {{ $t->description }}
                            @if ($t->remittance)
                                <br><small>Remittance: <a href="{{ route('finance.remittances.show', $t->remittance) }}">{{ $t->remittance->courier }} #{{ $t->remittance->reference_no }}</a></small>
                            @endif
                        </td>
                        <td>{{ $t->category }}</td>
                        <td style="text-align:right;color:green;">
                            {{ $t->type === 'in' ? number_format((float) $t->amount, 2) : '' }}
                        </td>
                        <td style="text-align:right;color:#b00;">
                            {{ $t->type === 'out' ? number_format((float) $t->amount, 2) : '' }}
                        </td>
                        <td style="text-align:right;">{{ number_format($t->running_balance, 2) }}</td>
                        <td>
                            <a href="{{ route('finance.transactions.edit', $t) }}">edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;color:#999;padding:1rem;">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-finance::layouts.master>
