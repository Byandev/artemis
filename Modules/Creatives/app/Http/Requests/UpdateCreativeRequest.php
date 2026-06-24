<?php

namespace Modules\Creatives\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Name and picture URL must be unique within the workspace, ignoring
        // the creative currently being edited.
        $workspaceId = $this->route('workspace')->id;
        $creativeId = $this->route('creative')->id;

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('creatives', 'name')->where('workspace_id', $workspaceId)->ignore($creativeId),
            ],
            'creative_date' => ['sometimes', 'required', 'date'],
            'format' => ['sometimes', 'required', Rule::in(['video', 'image'])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'assigned_reviewer_ids' => ['sometimes', 'nullable', 'array'],
            'assigned_reviewer_ids.*' => ['integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'script' => ['sometimes', 'nullable', 'string'],
            // Media is a plain text link (e.g. Google Drive), not an uploaded image.
            'picture_url' => [
                'sometimes', 'required', 'string', 'max:2048',
                Rule::unique('creatives', 'picture_url')->where('workspace_id', $workspaceId)->ignore($creativeId),
            ],
            'reference_link' => ['nullable', 'string', 'max:2048'],
            'ads_status' => ['nullable', Rule::in(['pending', 'running', 'kill', 'scale'])],
            'ads_manager_link' => ['nullable', 'string', 'max:2048'],
            'ads_remarks' => ['nullable', 'string', 'max:2000'],
            'final_status' => ['sometimes', Rule::in(['for_approval', 'approved', 'for_revision'])],
            'caption' => ['sometimes', 'required', 'string', 'max:5000'],
            'headline' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A creative with this name already exists in this workspace.',
            'picture_url.unique' => 'A creative with this media link already exists in this workspace.',
        ];
    }
}
