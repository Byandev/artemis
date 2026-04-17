<x-finance::layouts.master>
    <div style="max-width:640px;margin:2rem auto;font-family:sans-serif;">
        <h1>New Transaction</h1>
        <form method="POST" action="{{ route('finance.transactions.store') }}">
            @include('finance::transactions._form')
            <button type="submit">Create</button>
            <a href="{{ route('finance.transactions.index') }}">Cancel</a>
        </form>
    </div>
</x-finance::layouts.master>
