<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Modules\Finance\Http\Requests\Concerns\SplitsShares;
use Modules\Finance\Models\TransactionType;

class TransactionRequest extends FormRequest
{
    use SplitsShares;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The in/out direction is no longer picked by hand — it follows the chosen
     * transaction type's nature (credit = in, debit = out). Derive it here so the
     * transaction type stays the single source of truth. Falls back to whatever
     * `type` was submitted when no (or an unknown) type is selected.
     */
    protected function prepareForValidation(): void
    {
        $typeId = $this->input('transaction_type_id');

        if (! $typeId) {
            return;
        }

        $nature = TransactionType::where('id', $typeId)
            ->where('workspace_id', $this->route('workspace')?->id)
            ->value('nature');

        if ($nature) {
            $this->merge(['type' => $nature === 'credit' ? 'in' : 'out']);
        }
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
            // requested_by / approved_by / charge_to reference workspace members.
            'requested_by' => ['nullable', $this->memberRule($workspaceId)],
            'approved_by' => ['nullable', $this->memberRule($workspaceId)],
            'department' => ['nullable', 'string', 'max:255'],
            // A transaction can be charged to several members, each bearing a
            // share of the amount. A blank share is split evenly (see shares()).
            'charge_to' => ['nullable', 'array'],
            'charge_to.*.user_id' => ['required', 'distinct', $this->memberRule($workspaceId)],
            'charge_to.*.amount' => ['nullable', 'numeric', 'min:0'],
            // A transaction can be charged to several products (normalized
            // order_details names), each bearing a share of the amount for the
            // per-product income statement. A blank share is split evenly.
            'products' => ['nullable', 'array'],
            'products.*.product' => ['required', 'string', 'max:191', 'distinct'],
            'products.*.amount' => ['nullable', 'numeric', 'min:0'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            // The fund request this entry settles. Scoped to the workspace so a
            // request from elsewhere cannot be attached.
            'fund_request_id' => [
                'nullable',
                Rule::exists('finance_fund_requests', 'id')->where('workspace_id', $workspaceId),
            ],
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

    /**
     * The charge-to and per-product shares must each account for the whole amount
     * — otherwise part of the expense would silently belong to nobody.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $amount = (float) $this->input('amount');

            $this->assertSharesCoverAmount($validator, 'charge_to', 'charge-to', $this->chargeToShares(), $amount);
            $this->assertSharesCoverAmount($validator, 'products', 'product', $this->productShares(), $amount);
        }];
    }

    /**
     * The submitted charge-to rows as `{user_id, amount}`, blank shares taking
     * an even cut of the remainder (see SplitsShares).
     *
     * @return list<array{user_id:int, amount:float}>
     */
    public function chargeToShares(): array
    {
        return array_map(
            fn ($row) => ['user_id' => (int) $row['user_id'], 'amount' => $row['amount']],
            $this->splitShares('charge_to', 'user_id', (float) $this->input('amount')),
        );
    }

    /**
     * The submitted product rows as `{product, amount}`, blank shares taking an
     * even cut of the remainder (see SplitsShares).
     *
     * @return list<array{product:string, amount:float}>
     */
    public function productShares(): array
    {
        return array_map(
            fn ($row) => ['product' => (string) $row['product'], 'amount' => $row['amount']],
            $this->splitShares('products', 'product', (float) $this->input('amount')),
        );
    }

    /**
     * Rule ensuring a user id belongs to the given workspace.
     */
    private function memberRule(?int $workspaceId): Exists
    {
        return Rule::exists('workspace_user', 'user_id')
            ->where(fn ($q) => $q->where('workspace_id', $workspaceId));
    }
}
