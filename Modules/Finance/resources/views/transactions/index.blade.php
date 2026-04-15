<x-finance::layouts.master>
    <div style="max-width:1100px;margin:2rem auto;font-family:sans-serif;">
        <h1>Transactions</h1>
        <p><a href="{{ route('finance.transactions.create') }}">+ New Transaction</a></p>

        <table width="100%" cellpadding="6" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f4f4f4;text-align:left;">
                    <th>Date</th><th>Account</th><th>Description</th>
                    <th>Category</th><th>Type</th>
                    <th style="text-align:right;">Amount</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $t)
                    <tr style="border-top:1px solid #eee;">
                        <td>{{ $t->date->toDateString() }}</td>
                        <td>{{ $t->account?->name }}</td>
                        <td>{{ $t->description }}</td>
                        <td>{{ $t->category }}</td>
                        <td>{{ strtoupper($t->type) }}</td>
                        <td style="text-align:right;">{{ number_format((float) $t->amount, 2) }}</td>
                        <td><a href="{{ route('finance.transactions.edit', $t) }}">edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:1rem;">{{ $transactions->links() }}</div>
    </div>
</x-finance::layouts.master>
