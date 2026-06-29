<?php

namespace Modules\Creatives\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');
        $workspaceId = $workspace instanceof Workspace ? $workspace->getKey() : $workspace;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Unique per workspace — a name may be reused in other workspaces.
                Rule::unique('creatives', 'name')->where('workspace_id', $workspaceId),
            ],
            'creative_date' => ['required', 'date'],
            'format' => ['required', Rule::in(['video', 'image'])],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'assigned_reviewer_ids' => ['sometimes', 'nullable', 'array'],
            'assigned_reviewer_ids.*' => ['integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'script' => ['nullable', 'string'],
            // Media is a plain text link (e.g. Google Drive), not an uploaded image.
            'picture_url' => [
                'required', 'string', 'max:2048',
                Rule::unique('creatives', 'picture_url')->where('workspace_id', $workspaceId),
            ],
            'reference_link' => ['nullable', 'string', 'max:2048'],
            'ads_status' => ['nullable', Rule::in(['pending', 'running', 'kill', 'scale'])],
            'ads_manager_link' => ['nullable', 'string', 'max:2048'],
            'ads_remarks' => ['nullable', 'string', 'max:2000'],
            'final_status' => ['sometimes', Rule::in(['for_approval', 'approved', 'for_revision'])],
            'caption' => ['required', 'string', 'max:5000'],
            'headline' => ['required', 'string', 'max:255'],
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
