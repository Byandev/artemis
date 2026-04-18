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
            'transaction_type' => ['nullable', Rule::in(['funds', 'profit_share', 'expenses', 'transfer', 'remittance', 'loan', 'loan_payment', 'refund', 'voided', 'courier_damaged_settlement'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'sub_category' => ['nullable', Rule::in([
                'ad_spent', 'cogs', 'subscription', 'shipping_fee',
                'operation_expense', 'salary', 'transfer_fee', 'seminar_fee', 'rent', 'others',
            ])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
