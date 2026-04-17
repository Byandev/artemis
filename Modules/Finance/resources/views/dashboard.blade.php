<x-finance::layouts.master>
    <div style="max-width:960px;margin:2rem auto;font-family:sans-serif;">
        <h1>Finance Dashboard</h1>

        @if (session('status'))
            <p style="color:green;">{{ session('status') }}</p>
        @endif

        <nav style="margin-bottom:1rem;">
            <a href="{{ route('finance.accounts.index') }}">Accounts</a> |
            <a href="{{ route('finance.transactions.index') }}">Transactions</a> |
            <a href="{{ route('finance.remittances.index') }}">Remittances</a>
        </nav>

        <section style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;">
            <div style="padding:1rem;border:1px solid #ddd;">
                <strong>Total Balance</strong>
                <div>{{ number_format($totalBalance, 2) }}</div>
            </div>
            <div style="padding:1rem;border:1px solid #ddd;">
                <strong>IN this month</strong>
                <div>{{ number_format($monthIn, 2) }}</div>
            </div>
            <div style="padding:1rem;border:1px solid #ddd;">
                <strong>OUT this month</strong>
                <div>{{ number_format($monthOut, 2) }}</div>
            </div>
            <div style="padding:1rem;border:1px solid #ddd;">
                <strong>Unreconciled Remittances</strong>
                <div>
                    <a href="{{ route('finance.remittances.index', ['unreconciled' => 1]) }}">{{ $unreconciledCount }}</a>
                </div>
            </div>
        </section>

        <h2 style="margin-top:2rem;">Accounts</h2>
        <table width="100%" cellpadding="6" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f4f4f4;text-align:left;">
                    <th>Name</th><th>Currency</th><th style="text-align:right;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accounts as $account)
                    <tr style="border-top:1px solid #eee;">
                        <td><a href="{{ route('finance.accounts.show', $account) }}">{{ $account->name }}</a></td>
                        <td>{{ $account->currency }}</td>
                        <td style="text-align:right;">{{ number_format((float) $account->current_balance, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-finance::layouts.master>
