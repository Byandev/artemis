<?php

namespace Modules\Finance\Http\Requests;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Http\Requests\Concerns\SplitsShares;
use Modules\Finance\Models\FundRequest;

class FundRequestRequest extends FormRequest
{
    use SplitsShares;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Workspace $workspace */
        $workspace = $this->route('workspace');

        // requested_by / charge_to must be members of this workspace.
        $memberRule = Rule::exists('workspace_user', 'user_id')
            ->where(fn ($q) => $q->where('workspace_id', $workspace->id));

        $isAdSpent = $this->input('template') === FundRequest::TEMPLATE_AD_SPENT;

        return [
            'template' => ['required', Rule::in(FundRequest::TEMPLATES)],
            'request_date' => ['required', 'date'],
            // reference_no is system-generated on create (see the controller) and
            // never changes, so it is not accepted from the client.
            'requested_by' => ['required', $memberRule],
            // A request can be charged to several members, each bearing a share
            // of the amount. A blank share is split evenly (see SplitsShares).
            'charge_to' => ['required', 'array', 'min:1'],
            'charge_to.*.user_id' => ['required', 'distinct', $memberRule],
            'charge_to.*.amount' => ['nullable', 'numeric', 'min:0'],
            'gotyme_number' => ['nullable', 'string', 'max:50'],
            'purpose' => ['required', 'string', 'max:2000'],
            // An ad spend request derives its amount from the line items, so the
            // client's figure is ignored and may be omitted there (see the
            // controller). `nullable` keeps an omitted amount from tripping the
            // numeric rule; `requiredIf` still demands one on a blank request.
            'amount_requested' => [Rule::requiredIf(! $isAdSpent), 'nullable', 'numeric', 'min:0'],
            'date_needed' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            // The products the request covers, each bearing a share of the
            // amount. Set on every template, independently of the items below.
            'products' => ['nullable', 'array'],
            'products.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->where('workspace_id', $workspace->id),
            ],
            'products.*.amount' => ['nullable', 'numeric', 'min:0'],

            'items' => $isAdSpent ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            // Scoped to the workspace rather than to the requester's own pages:
            // the picker only offers pages assigned to the signed-in user, but an
            // approver editing someone else's request must not trip over that.
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('workspace_id', $workspace->id),
            ],
            'items.*.page_id' => [
                'nullable',
                Rule::exists('pages', 'id')->where('workspace_id', $workspace->id),
            ],
            'items.*.creatives_running' => ['required', 'integer', 'min:0'],
            'items.*.budget_per_day' => ['required', 'numeric', 'min:0'],
            'items.*.days' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * The charge-to and product shares must each account for the whole request —
     * otherwise part of it would silently belong to nobody.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $total = $this->requestTotal();

            $this->assertSharesCoverAmount($validator, 'charge_to', 'charge-to', $this->chargeToShares(), $total);
            $this->assertSharesCoverAmount($validator, 'products', 'product', $this->productShares(), $total);
        }];
    }

    /**
     * The amount the shares have to cover. An Ad Spent request's total comes
     * from its line items — the controller recomputes it on save regardless, so
     * validating against the client's `amount_requested` would check the shares
     * against a figure that is about to be overwritten.
     */
    public function requestTotal(): float
    {
        if ($this->input('template') !== FundRequest::TEMPLATE_AD_SPENT) {
            return round((float) $this->input('amount_requested'), 2);
        }

        $total = 0.0;

        foreach ((array) $this->input('items', []) as $item) {
            if (is_array($item)) {
                $total += (float) ($item['budget_per_day'] ?? 0) * (float) ($item['days'] ?? 0);
            }
        }

        return round($total, 2);
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
            $this->splitShares('charge_to', 'user_id', $this->requestTotal()),
        );
    }

    /**
     * The submitted product rows as `{product_id, amount}`, blank shares taking
     * an even cut of the remainder (see SplitsShares).
     *
     * @return list<array{product_id:int, amount:float}>
     */
    public function productShares(): array
    {
        return array_map(
            fn ($row) => ['product_id' => (int) $row['product_id'], 'amount' => $row['amount']],
            $this->splitShares('products', 'product_id', $this->requestTotal()),
        );
    }

    public function messages(): array
    {
        return [
            'requested_by.exists' => 'The requester must be a member of this workspace.',
            'charge_to.required' => 'Charge the request to at least one member.',
            'charge_to.*.user_id.required' => 'Select a member for every charge-to row.',
            'charge_to.*.user_id.exists' => 'The charge-to user must be a member of this workspace.',
            'charge_to.*.user_id.distinct' => 'Each member can only be charged once.',
            'products.*.product_id.required' => 'Select a product for every product row.',
            'products.*.product_id.exists' => 'The product must belong to this workspace.',
            'products.*.product_id.distinct' => 'Each product can only be listed once.',
            'items.required' => 'Add at least one item to an Ad Spent request.',
            'items.*.product_id.required' => 'Select a product for every item.',
            'items.*.product_id.exists' => 'The product must belong to this workspace.',
            'items.*.page_id.exists' => 'The page must belong to this workspace.',
        ];
    }

    public function attributes(): array
    {
        return [
            'charge_to.*.user_id' => 'charge-to user',
            'products.*.product_id' => 'product',
            'items.*.product_id' => 'product',
            'items.*.page_id' => 'page',
            'items.*.creatives_running' => 'number of creatives',
            'items.*.budget_per_day' => 'budget per day',
            'items.*.days' => 'number of days',
        ];
    }
}
