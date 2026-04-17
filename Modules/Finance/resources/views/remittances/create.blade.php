<x-finance::layouts.master>
    <div style="max-width:640px;margin:2rem auto;font-family:sans-serif;">
        <h1>New Remittance</h1>
        <form method="POST" action="{{ route('finance.remittances.store') }}">
            @include('finance::remittances._form')
            <button type="submit">Create</button>
            <a href="{{ route('finance.remittances.index') }}">Cancel</a>
        </form>
    </div>
</x-finance::layouts.master>
