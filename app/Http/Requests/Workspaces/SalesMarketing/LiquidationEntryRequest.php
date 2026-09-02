<?php

namespace App\Http\Requests\Workspaces\SalesMarketing;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One line of a wallet's liquidation ledger. The fields are named for the
 * finance_transactions columns they land in: the ledger's Transaction column is
 * the workspace's transaction type, Type Expenses is the free-text description,
 * Credit/Debit is `type`, and Remarks is `notes`.
 *
 * Authorization is handled by the controller/gate; the request only validates.
 */
class LiquidationEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Workspace $workspace */
        $workspace = $this->route('workspace');

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'transaction_type_id' => [
                'required',
                Rule::exists('finance_transaction_types', 'id')
                    ->where('workspace_id', $workspace->id),
            ],
            'description' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
