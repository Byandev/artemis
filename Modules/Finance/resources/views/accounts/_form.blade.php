@csrf
<div style="margin-bottom:1rem;">
    <label>Name<br>
        <input type="text" name="name" value="{{ old('name', $account->name) }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Opening Balance<br>
        <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', $account->opening_balance ?? 0) }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Currency<br>
        <input type="text" maxlength="3" name="currency" value="{{ old('currency', $account->currency ?? 'PHP') }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Notes<br>
        <textarea name="notes" rows="3" cols="40">{{ old('notes', $account->notes) }}</textarea>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $account->is_active ?? true) ? 'checked' : '' }}>
        Active
    </label>
</div>
@if ($errors->any())
    <ul style="color:red;">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
@endif
