<?php

namespace Modules\Finance\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestFundRequest extends FormRequest
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

        return [
            'request_date' => ['required', 'date'],
            // reference_no is system-generated on create (see the controller) and
            // never changes, so it is not accepted from the client.
            'requested_by' => ['required', $memberRule],
            'charge_to' => ['required', $memberRule],
            'purpose' => ['required', 'string', 'max:2000'],
            'amount_requested' => ['required', 'numeric', 'min:0'],
            'date_needed' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_by.exists' => 'The requester must be a member of this workspace.',
            'charge_to.exists' => 'The charge-to user must be a member of this workspace.',
        ];
    }
}
