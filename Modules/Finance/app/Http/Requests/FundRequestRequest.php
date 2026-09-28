<?php

namespace Modules\Finance\Http\Requests;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Http\Requests\Concerns\SplitsShares;
use Modules\Finance\Models\FundRequest;
use Modules\Finance\Models\FundRequestAttachment;

class FundRequestRequest extends FormRequest
{
    use SplitsShares;

    /**
     * What an attachment may be: scans and photos (`heif` alongside `heic`, as
     * iOS photos are often detected as the former), PDFs, and office files.
     */
    public const ATTACHMENT_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'];

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
            'transaction_type_id' => [
                'nullable',
                Rule::exists('finance_transaction_types', 'id')->where('workspace_id', $workspace->id),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('workspace_id', $workspace->id),
            ],
            // A request can be charged to several members, each bearing a share
            // of the amount. A blank share is split evenly (see SplitsShares).
            'charge_to' => ['required', 'array', 'min:1'],
            'charge_to.*.user_id' => ['required', 'distinct', $memberRule],
            'charge_to.*.amount' => ['nullable', 'numeric', 'min:0'],
            'amount_requested' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            // The products the request covers, each bearing a share of the amount.
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
                Rule::exists('finance_fund_request_checklists', 'id')
                    ->where('transaction_type_id', $this->input('transaction_type_id') ?: 0),
            ],

            // Files keyed by the attachment they answer: attachments[<id>] = file.
            // That each key is one of the chosen type's attachments is checked
            // in after(), since the keys aren't values a rule can see.
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:'.implode(',', self::ATTACHMENT_MIMES), 'max:10240'],
            // Attachment ids whose file on file should be removed (edit only).
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
                ? FundRequestAttachment::where('transaction_type_id', $this->input('transaction_type_id'))->pluck('name', 'id')
                : collect();
            $uploaded = array_map('intval', array_keys($this->file('attachments', [])));

            foreach ($uploaded as $attachmentId) {
                if (! $required->has($attachmentId)) {
                    $validator->errors()->add("attachments.{$attachmentId}", 'This attachment is not one the selected type calls for.');
                }
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
     * The attachment ids the request being edited already has a file for; none
     * when creating.
     *
     * @return list<int>
     */
    protected function savedAttachmentIds(): array
    {
        $fundRequest = $this->route('requestFund');

        if (! $fundRequest instanceof FundRequest) {
            return [];
        }

        return $fundRequest->getMedia(FundRequest::ATTACHMENTS_COLLECTION)
            ->map(fn ($media) => (int) $media->getCustomProperty('attachment_id'))
            ->all();
    }

    /** The amount the charge-to and product shares have to cover. */
    public function requestTotal(): float
    {
        return round((float) $this->input('amount_requested'), 2);
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
            'charge_to.required' => 'Charge the request to at least one member.',
            'charge_to.*.user_id.required' => 'Select a member for every charge-to row.',
            'charge_to.*.user_id.exists' => 'The charge-to user must be a member of this workspace.',
            'charge_to.*.user_id.distinct' => 'Each member can only be charged once.',
            'products.*.product_id.required' => 'Select a product for every product row.',
            'products.*.product_id.exists' => 'The product must belong to this workspace.',
            'products.*.product_id.distinct' => 'Each product can only be listed once.',
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
        ];
    }
}
