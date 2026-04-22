<x-finance::layouts.master>
    <div style="max-width:960px;margin:2rem auto;font-family:sans-serif;">
        <h1>Accounts</h1>
        @if (session('status'))
            <p style="color:green;">{{ session('status') }}</p>
        @endif
        <p><a href="{{ route('finance.accounts.create') }}">+ New Account</a></p>

        <table width="100%" cellpadding="6" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f4f4f4;text-align:left;">
                    <th>Name</th><th>Currency</th><th>Status</th>
                    <th style="text-align:right;">Current Balance</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accounts as $account)
                    <tr style="border-top:1px solid #eee;">
                        <td><a href="{{ route('finance.accounts.show', $account) }}">{{ $account->name }}</a></td>
                        <td>{{ $account->currency }}</td>
                        <td>{{ $account->is_active ? 'active' : 'inactive' }}</td>
                        <td style="text-align:right;">{{ number_format((float) $account->current_balance, 2) }}</td>
                        <td><a href="{{ route('finance.accounts.edit', $account) }}">edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-finance::layouts.master>
