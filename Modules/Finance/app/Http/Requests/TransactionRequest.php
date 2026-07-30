<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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
            // requested_by / approved_by / charge_to reference workspace members.
            'requested_by' => ['nullable', $this->memberRule($workspaceId)],
            'approved_by' => ['nullable', $this->memberRule($workspaceId)],
            'department' => ['nullable', 'string', 'max:255'],
            // A transaction can be charged to several members, each bearing a
            // share of the amount. A blank share is split evenly (see shares()).
            'charge_to' => ['nullable', 'array'],
            'charge_to.*.user_id' => ['required', 'distinct', $this->memberRule($workspaceId)],
            'charge_to.*.amount' => ['nullable', 'numeric', 'min:0'],
            // Optional product tag (normalized order_details name) for the
            // per-product income statement.
            'product' => ['nullable', 'string', 'max:191'],
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

    /**
     * The shares must account for the whole amount — otherwise part of the
     * expense would silently belong to nobody.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $shares = $this->chargeToShares();

            if ($shares === []) {
                return;
            }

            $allocated = array_sum(array_column($shares, 'amount'));
            $amount = round((float) $this->input('amount'), 2);

            if (abs($allocated - $amount) >= 0.01) {
                $validator->errors()->add('charge_to', sprintf(
                    'The charge-to shares add up to %s, but the amount is %s.',
                    number_format($allocated, 2),
                    number_format($amount, 2),
                ));
            }
        }];
    }

    /**
     * The submitted charge-to rows as `{user_id, amount}`, with any blank share
     * taking an even cut of whatever the explicit shares left over. Works in
     * cents so an uneven split (100 across 3) loses nothing to rounding.
     *
     * @return list<array{user_id:int, amount:float}>
     */
    public function chargeToShares(): array
    {
        $rows = [];

        foreach ((array) $this->input('charge_to', []) as $row) {
            if (! is_array($row) || ! isset($row['user_id']) || $row['user_id'] === '') {
                continue;
            }

            $share = $row['amount'] ?? null;

            $rows[] = [
                'user_id' => (int) $row['user_id'],
                'cents' => ($share === null || $share === '') ? null : (int) round((float) $share * 100),
            ];
        }

        if ($rows === []) {
            return [];
        }

        $blank = array_keys(array_filter($rows, fn ($r) => $r['cents'] === null));

        if ($blank !== []) {
            $explicit = array_sum(array_column($rows, 'cents'));
            $left = max((int) round((float) $this->input('amount') * 100) - $explicit, 0);
            $each = intdiv($left, count($blank));
            $odd = $left - ($each * count($blank));

            // The leftover cents go to the first few users rather than vanishing.
            foreach ($blank as $i => $index) {
                $rows[$index]['cents'] = $each + ($i < $odd ? 1 : 0);
            }
        }

        return array_map(fn ($r) => [
            'user_id' => $r['user_id'],
            'amount' => round($r['cents'] / 100, 2),
        ], $rows);
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
