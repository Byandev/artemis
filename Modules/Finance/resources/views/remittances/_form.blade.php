@csrf
<div style="margin-bottom:1rem;">
    <label>Courier<br>
        <input type="text" name="courier" value="{{ old('courier', $remittance->courier) }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Date<br>
        <input type="date" name="date" value="{{ old('date', optional($remittance->date)->toDateString() ?? $remittance->date) }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Reference No<br>
        <input type="text" name="reference_no" value="{{ old('reference_no', $remittance->reference_no) }}">
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Gross Amount<br>
        <input type="number" step="0.01" min="0" name="gross_amount" value="{{ old('gross_amount', $remittance->gross_amount ?? 0) }}" required>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Deductions<br>
        <input type="number" step="0.01" min="0" name="deductions" value="{{ old('deductions', $remittance->deductions ?? 0) }}" required>
    </label>
</div>
<p><small>Net amount is computed automatically (gross - deductions).</small></p>
<div style="margin-bottom:1rem;">
    <label>Status<br>
        <select name="status" required>
            @foreach (['pending', 'remitted'] as $s)
                <option value="{{ $s }}" @selected(old('status', $remittance->status) === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </label>
</div>
<div style="margin-bottom:1rem;">
    <label>Notes<br>
        <textarea name="notes" rows="3" cols="40">{{ old('notes', $remittance->notes) }}</textarea>
    </label>
</div>
@if ($errors->any())
    <ul style="color:red;">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
@endif
