<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['in', 'out'])],
            'transaction_type' => ['required', Rule::in(['funds', 'profit_share', 'expenses', 'transfer', 'remittance'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'category' => ['required', Rule::in(['remittance', 'expense', 'transfer', 'other'])],
            'remittance_id' => ['nullable', 'exists:finance_remittances,id', 'required_if:category,remittance'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('category') !== 'remittance') {
            $this->merge(['remittance_id' => null]);
        }
    }
}
