<x-finance::layouts.master>
    <div style="max-width:600px;margin:2rem auto;font-family:sans-serif;">
        <h1>New Account</h1>
        <form method="POST" action="{{ route('finance.accounts.store') }}">
            @include('finance::accounts._form')
            <button type="submit">Create</button>
            <a href="{{ route('finance.accounts.index') }}">Cancel</a>
        </form>
    </div>
</x-finance::layouts.master>
