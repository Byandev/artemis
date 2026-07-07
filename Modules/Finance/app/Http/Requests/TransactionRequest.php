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
        // Dynamic transaction types are workspace-scoped; validate the submitted
        // id belongs to this workspace's finance_transaction_types. The legacy
        // `transaction_type` enum is left as-is for backward compatibility.
        $workspaceId = $this->route('workspace')?->id;

        return [
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['in', 'out'])],
            'transaction_type' => ['nullable', Rule::in(['funds', 'profit_share', 'expenses', 'transfer', 'remittance', 'loan', 'loan_payment', 'refund', 'voided', 'courier_damaged_settlement', 'capex', 'interest', 'interest_fee'])],
            'transaction_type_id' => [
                'nullable',
                Rule::exists('finance_transaction_types', 'id')->where('workspace_id', $workspaceId),
            ],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'charge_to' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'posted'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'running_balance' => ['nullable', 'numeric'],
            'position' => ['nullable', 'integer', 'min:1'],
            'sub_category' => ['nullable', Rule::in([
                'ad_spent', 'cogs', 'subscription', 'shipping_fee', 'delivery_fee',
                'operation_expense', 'salary', 'transfer_fee', 'seminar_fee', 'rent', 'capex_payment', 'others',
            ])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
