<?php

namespace Modules\Finance\Http\Requests;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Http\Requests\Concerns\SplitsShares;
use Modules\Finance\Models\FundRequest;
use Modules\Finance\Models\FundRequestAttachmentRequirement;
use Modules\Finance\Models\TransactionType;
use Modules\Products\Models\Product;

class FundRequestRequest extends FormRequest
{
    use SplitsShares;

    /**
     * What an attachment may be: scans and photos (`heif` alongside `heic`, as
     * iOS photos are often detected as the former), PDFs, and office files.
     */
    public const ATTACHMENT_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'];

    /** Whether every attachment the chosen type calls for must have a file. */
    public const ATTACHMENTS_REQUIRED = false;

    /** Memo for isPerProduct(). */
    private ?bool $perProduct = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Workspace $workspace */
        $workspace = $this->route('workspace');

        // charge_to must reference members of this workspace.
        $memberRule = Rule::exists('workspace_user', 'user_id')
            ->where(fn ($q) => $q->where('workspace_id', $workspace->id));

        return [
            // request_date and requested_by are stamped on create from the
            // creation time and the signed-in user (see the controller), so they
            // are not accepted from the client. reference_no is likewise
            // system-generated and never changes.
            // A fund-requestable type, or (on an edit) the type the request
            // already has, should that type since have been switched off.
            'transaction_type_id' => [
                'nullable',
                Rule::exists('finance_transaction_types', 'id')
                    ->where('workspace_id', $workspace->id)
                    ->where(fn ($q) => $q->where('fund_requestable', true)
                        ->when($this->route('requestFund')?->transaction_type_id, fn ($q, $id) => $q->orWhere('id', $id))),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('workspace_id', $workspace->id),
            ],
            // A request can be charged to several members, each bearing a share
            // of the amount, or to no one yet. A blank share is split evenly
            // (see SplitsShares).
            'charge_to' => ['nullable', 'array'],
            'charge_to.*.user_id' => ['required', 'distinct', $memberRule],
            'charge_to.*.amount' => ['nullable', 'numeric', 'min:0'],
            // What the funds are for, line by line. The amount requested is
            // their total and each row's amount its quantity × unit price, both
            // worked out here rather than taken from the client.
            // On a per-product type each row is for a product instead of a typed
            // name; the name is then that product's, set in particulars().
            'particulars' => ['required', 'array', 'min:1'],
            'particulars.*.name' => [Rule::requiredIf(! $this->isPerProduct()), 'nullable', 'string', 'max:255'],
            'particulars.*.product_id' => [
                Rule::requiredIf($this->isPerProduct()),
                'nullable',
                Rule::exists('products', 'id')->where('workspace_id', $workspace->id),
            ],
            'particulars.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'particulars.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            // Whether the funds have to be liquidated, and by when. The deadline
            // is only kept when liquidation is required (see the controller).
            'liquidation_required' => ['boolean'],
            'liquidation_deadline' => [
                Rule::requiredIf(fn () => $this->boolean('liquidation_required')),
                'nullable',
                'date',
            ],

            // How the funds are released, which may be left for later. Online
            // banking and e-wallets send them to an account, which has to be
            // given; for the others the account fields are dropped (see the
            // controller).
            'payment_method' => ['nullable', Rule::in(array_keys(FundRequest::PAYMENT_METHODS))],
            'bank_name' => [Rule::requiredIf($this->needsAccount()), 'nullable', 'string', 'max:255'],
            'account_name' => [Rule::requiredIf($this->needsAccount()), 'nullable', 'string', 'max:255'],
            'account_number' => [Rule::requiredIf($this->needsAccount()), 'nullable', 'string', 'max:255'],

            // The products the request covers, each bearing a share of the amount.
            // Not asked for on a per-product type, whose particulars say it.
            'products' => ['nullable', 'array'],
            'products.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->where('workspace_id', $workspace->id),
            ],
            'products.*.amount' => ['nullable', 'numeric', 'min:0'],

            // The items ticked off on the chosen type's checklist.
            'checklist_ids' => ['nullable', 'array'],
            'checklist_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('finance_fund_request_transaction_type_checklists', 'checklist_requirement_id')
                    ->where('transaction_type_id', $this->input('transaction_type_id') ?: 0),
            ],

            // Files keyed by the attachment requirement they answer:
            // attachments[<requirement id>] = file. That each key is one the
            // chosen type calls for is checked in after(), since the keys
            // aren't values a rule can see.
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'mimes:'.implode(',', self::ATTACHMENT_MIMES), 'max:10240'],
            // Attachment requirement ids whose file should be removed (edit only).
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    /**
     * The charge-to and product shares must each account for the whole request —
     * otherwise part of it would silently belong to nobody — and every
     * attachment the chosen type calls for must have a file.
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

            $required = $this->input('transaction_type_id')
                ? FundRequestAttachmentRequirement::whereRelation('transactionTypes', 'finance_transaction_types.id', $this->input('transaction_type_id'))->pluck('name', 'id')
                : collect();
            $uploaded = array_map('intval', array_keys($this->file('attachments', [])));

            foreach ($uploaded as $attachmentId) {
                if (! $required->has($attachmentId)) {
                    $validator->errors()->add("attachments.{$attachmentId}", 'This attachment is not one the selected type calls for.');
                }
            }

            // Attachments are optional for now (nullable): flip this on to
            // require a file for every attachment the type calls for again.
            if (! self::ATTACHMENTS_REQUIRED) {
                return;
            }

            // Every attachment the type calls for needs a file: a new upload, or
            // (on an edit) the one already on file, unless it is being removed.
            $kept = array_diff($this->savedAttachmentIds(), array_map('intval', $this->input('remove_attachments', [])));

            foreach ($required as $attachmentId => $name) {
                if (! in_array($attachmentId, $uploaded, true) && ! in_array($attachmentId, $kept, true)) {
                    $validator->errors()->add("attachments.{$attachmentId}", "Upload the {$name}.");
                }
            }
        }];
    }

    /**
     * The attachment requirement ids the request being edited already has a
     * file for; none when creating.
     *
     * @return list<int>
     */
    protected function savedAttachmentIds(): array
    {
        $fundRequest = $this->route('requestFund');

        if (! $fundRequest instanceof FundRequest) {
            return [];
        }

        return $fundRequest->attachments()->pluck('attachment_requirement_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Whether the chosen transaction type is fund-requestable per product,
     * its particulars each being for a product.
     */
    public function isPerProduct(): bool
    {
        return $this->perProduct ??= (bool) TransactionType::where('workspace_id', $this->route('workspace')->id)
            ->find($this->input('transaction_type_id'))
            ?->fund_requestable_per_product;
    }

    /**
     * The submitted particulars as `{product_id, name, quantity, unit_price,
     * amount}`, each amount being its quantity × unit price. On a per-product
     * type the name is the product's; otherwise there is no product.
     *
     * @return list<array{product_id:?int, name:string, quantity:float, unit_price:float, amount:float}>
     */
    public function particulars(): array
    {
        $rows = array_values($this->input('particulars', []));

        $names = $this->isPerProduct()
            ? Product::where('workspace_id', $this->route('workspace')->id)
                ->whereIn('id', array_filter(array_column($rows, 'product_id')))
                ->pluck('name', 'id')
            : collect();

        return array_map(function ($row) use ($names) {
            $productId = $this->isPerProduct() ? (int) $row['product_id'] : null;

            return [
                'product_id' => $productId,
                'name' => $productId ? ($names[$productId] ?? '') : trim($row['name']),
                'quantity' => round((float) $row['quantity'], 2),
                'unit_price' => round((float) $row['unit_price'], 2),
                'amount' => round(round((float) $row['quantity'], 2) * round((float) $row['unit_price'], 2), 2),
            ];
        }, $rows);
    }

    /** Whether the chosen payment method sends the funds to an account. */
    public function needsAccount(): bool
    {
        return in_array($this->input('payment_method'), FundRequest::PAYMENT_METHODS_WITH_ACCOUNT, true);
    }

    /**
     * The amount requested — the particulars' total — which the charge-to and
     * product shares have to cover.
     */
    public function requestTotal(): float
    {
        return round(array_sum(array_column($this->particulars(), 'amount')), 2);
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
     * an even cut of the remainder (see SplitsShares). On a per-product type
     * they come from the particulars instead: each product's share is the total
     * of its rows, so a transaction filled in from the request still gets them.
     *
     * @return list<array{product_id:int, amount:float}>
     */
    public function productShares(): array
    {
        if ($this->isPerProduct()) {
            return collect($this->particulars())
                ->groupBy('product_id')
                ->map(fn ($rows, $productId) => [
                    'product_id' => (int) $productId,
                    'amount' => round($rows->sum('amount'), 2),
                ])
                ->values()
                ->all();
        }

        return array_map(
            fn ($row) => ['product_id' => (int) $row['product_id'], 'amount' => $row['amount']],
            $this->splitShares('products', 'product_id', $this->requestTotal()),
        );
    }

    public function messages(): array
    {
        return [
            'bank_name.required' => 'Enter the bank or e-wallet.',
            'account_name.required' => 'Enter the account name.',
            'account_number.required' => 'Enter the account number.',
            'liquidation_deadline.required' => 'Set the liquidation deadline.',
            'particulars.required' => 'Add at least one particular.',
            'particulars.min' => 'Add at least one particular.',
            'particulars.*.name.required' => 'Name every particular.',
            'particulars.*.product_id.required' => 'Pick a product for every particular.',
            'particulars.*.product_id.exists' => 'The product must belong to this workspace.',
            'particulars.*.quantity.gt' => 'The quantity must be more than zero.',
            'charge_to.*.user_id.required' => 'Select a member for every charge-to row.',
            'charge_to.*.user_id.exists' => 'The charge-to user must be a member of this workspace.',
            'charge_to.*.user_id.distinct' => 'Each member can only be charged once.',
            'products.*.product_id.required' => 'Select a product for every product row.',
            'products.*.product_id.exists' => 'The product must belong to this workspace.',
            'products.*.product_id.distinct' => 'Each product can only be listed once.',
            'transaction_type_id.exists' => 'Pick a transaction type that fund requests can use.',
            'checklist_ids.*.exists' => 'That checklist item is not on the selected type\'s checklist.',
            'attachments.*.mimes' => 'Upload an image, PDF, Word, Excel or CSV file.',
            'attachments.*.max' => 'The file may not be larger than 10 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'charge_to.*.user_id' => 'charge-to user',
            'products.*.product_id' => 'product',
            'particulars.*.name' => 'particular name',
            'particulars.*.quantity' => 'quantity',
            'particulars.*.unit_price' => 'unit price',
        ];
    }
}
