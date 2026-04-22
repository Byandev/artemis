<x-finance::layouts.master>
    <div style="max-width:600px;margin:2rem auto;font-family:sans-serif;">
        <h1>Edit Account</h1>
        <form method="POST" action="{{ route('finance.accounts.update', $account) }}">
            @method('PUT')
            @include('finance::accounts._form')
            <button type="submit">Update</button>
            <a href="{{ route('finance.accounts.show', $account) }}">Cancel</a>
        </form>

        <form method="POST" action="{{ route('finance.accounts.destroy', $account) }}" style="margin-top:2rem;"
              onsubmit="return confirm('Delete this account and all its transactions?');">
            @csrf
            @method('DELETE')
            <button type="submit" style="color:red;">Delete Account</button>
        </form>
    </div>
</x-finance::layouts.master>
