<x-finance::layouts.master>
    <div style="max-width:640px;margin:2rem auto;font-family:sans-serif;">
        <h1>Edit Transaction</h1>
        <form method="POST" action="{{ route('finance.transactions.update', $transaction) }}">
            @method('PUT')
            @include('finance::transactions._form')
            <button type="submit">Update</button>
            <a href="{{ route('finance.accounts.show', $transaction->account_id) }}">Cancel</a>
        </form>

        <form method="POST" action="{{ route('finance.transactions.destroy', $transaction) }}" style="margin-top:2rem;"
              onsubmit="return confirm('Delete this transaction?');">
            @csrf
            @method('DELETE')
            <button type="submit" style="color:red;">Delete</button>
        </form>
    </div>
</x-finance::layouts.master>
