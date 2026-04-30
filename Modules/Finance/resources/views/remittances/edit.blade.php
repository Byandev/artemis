<x-finance::layouts.master>
    <div style="max-width:640px;margin:2rem auto;font-family:sans-serif;">
        <h1>Edit Remittance</h1>
        <form method="POST" action="{{ route('finance.remittances.update', $remittance) }}">
            @method('PUT')
            @include('finance::remittances._form')
            <button type="submit">Update</button>
            <a href="{{ route('finance.remittances.show', $remittance) }}">Cancel</a>
        </form>

        <form method="POST" action="{{ route('finance.remittances.destroy', $remittance) }}" style="margin-top:2rem;"
              onsubmit="return confirm('Delete this remittance?');">
            @csrf
            @method('DELETE')
            <button type="submit" style="color:red;">Delete</button>
        </form>
    </div>
</x-finance::layouts.master>
