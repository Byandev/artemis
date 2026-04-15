<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->route('workspace')?->id;
        $remittanceId = $this->route('remittance')?->id;

        return [
            'courier' => ['required', 'string', 'max:255'],
            'soa_number' => [
                'required', 'string', 'max:255',
                Rule::unique('finance_remittances', 'soa_number')
                    ->where(fn ($q) => $q->where('workspace_id', $workspaceId))
                    ->ignore($remittanceId),
            ],
            'billing_date_from' => ['required', 'date'],
            'billing_date_to' => ['required', 'date', 'after_or_equal:billing_date_from'],
            'gross_cod' => ['required', 'numeric', 'min:0'],
            'cod_fee' => ['required', 'numeric', 'min:0'],
            'cod_fee_vat' => ['required', 'numeric', 'min:0'],
            'shipping_fee' => ['required', 'numeric', 'min:0'],
            'return_shipping' => ['required', 'numeric', 'min:0'],
            'net_amount' => ['required', 'numeric'],
            'status' => ['required', Rule::in(['pending', 'remitted'])],
            'transaction_id' => ['nullable', 'exists:finance_transactions,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
