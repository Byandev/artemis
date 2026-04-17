@csrf
<div style="margin-bottom:1rem;">
    <label>Account<br>
        <select name="account_id" required>
            <option value="">-- select --</option>
            @foreach ($accounts as $a)
                <option value="{{ $a->id }}" @selected(old('account_id', $transaction->account_id) == $a->id)>
                    {{ $a->name }} ({{ $a->currency }})
                </option>
            @endforeach
        </select>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Date<br>
        <input type="date" name="date" value="{{ old('date', optional($transaction->date)->toDateString() ?? $transaction->date) }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Description<br>
        <input type="text" name="description" value="{{ old('description', $transaction->description) }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Type<br>
        <select name="type" required>
            @foreach (['in' => 'IN (deposit)', 'out' => 'OUT (withdrawal)'] as $v => $l)
                <option value="{{ $v }}" @selected(old('type', $transaction->type) === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Amount<br>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $transaction->amount) }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Category<br>
        <select name="category" id="category-select" required onchange="document.getElementById('remittance-row').style.display = this.value === 'remittance' ? 'block' : 'none';">
            @foreach (['remittance', 'expense', 'transfer', 'other'] as $c)
                <option value="{{ $c }}" @selected(old('category', $transaction->category) === $c)>{{ $c }}</option>
            @endforeach
        </select>
    </label>
</div>
<div id="remittance-row" style="margin-bottom:1rem;display:{{ old('category', $transaction->category) === 'remittance' ? 'block' : 'none' }};">
    <label>Linked Remittance<br>
        <select name="remittance_id">
            <option value="">-- none --</option>
            @foreach ($remittances as $r)
                <option value="{{ $r->id }}" @selected(old('remittance_id', $transaction->remittance_id) == $r->id)>
                    {{ $r->date->toDateString() }} &middot; {{ $r->courier }} &middot; {{ $r->reference_no }} &middot; net {{ number_format((float) $r->net_amount, 2) }}
                </option>
            @endforeach
        </select>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Notes<br>
        <textarea name="notes" rows="3" cols="40">{{ old('notes', $transaction->notes) }}</textarea>
    </label>
</div>
@if ($errors->any())
    <ul style="color:red;">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
@endif
