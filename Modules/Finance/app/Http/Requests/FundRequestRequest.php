<?php

namespace Modules\Finance\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\FundRequest;

class FundRequestRequest extends FormRequest
{
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
            'charge_to' => ['required', $memberRule],
            'gotyme_number' => ['nullable', 'string', 'max:50'],
            'purpose' => ['required', 'string', 'max:2000'],
            // An ad spend request derives its amount from the line items, so the
            // client's figure is ignored and may be omitted there (see the
            // controller). `nullable` keeps an omitted amount from tripping the
            // numeric rule; `requiredIf` still demands one on a blank request.
            'amount_requested' => [Rule::requiredIf(! $isAdSpent), 'nullable', 'numeric', 'min:0'],
            'date_needed' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],

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

    public function messages(): array
    {
        return [
            'requested_by.exists' => 'The requester must be a member of this workspace.',
            'charge_to.exists' => 'The charge-to user must be a member of this workspace.',
            'items.required' => 'Add at least one item to an Ad Spent request.',
            'items.*.product_id.required' => 'Select a product for every item.',
            'items.*.product_id.exists' => 'The product must belong to this workspace.',
            'items.*.page_id.exists' => 'The page must belong to this workspace.',
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.product_id' => 'product',
            'items.*.page_id' => 'page',
            'items.*.creatives_running' => 'number of creatives',
            'items.*.budget_per_day' => 'budget per day',
            'items.*.days' => 'number of days',
        ];
    }
}
