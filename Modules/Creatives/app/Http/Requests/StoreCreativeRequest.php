<?php

namespace Modules\Creatives\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Creatives\Http\Requests\Concerns\ChecksMediaMatchesFormat;

class StoreCreativeRequest extends FormRequest
{
    use ChecksMediaMatchesFormat;

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
            // Media is either a link (e.g. Google Drive) or a file uploaded
            // into Artemis — one of the two is required.
            'picture_url' => [
                'nullable', 'required_without_all:media_key,media_file', 'string', 'max:2048',
                Rule::unique('creatives', 'picture_url')->where('workspace_id', $workspaceId),
            ],
            // A key from CreativesController@presignMedia: the usual path, where
            // the browser has already put the file in the bucket. `media_file`
            // is the fallback for disks that cannot sign an upload.
            'media_key' => ['nullable', 'string'],
            // The browser's file name, kept for display and download.
            'media_name' => ['nullable', 'string', 'max:255'],
            'media_file' => ['nullable', 'file', 'mimetypes:image/*,video/*'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->checkMediaMatchesFormat($validator, $this->input('format')));
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A creative with this name already exists in this workspace.',
            'picture_url.unique' => 'A creative with this media link already exists in this workspace.',
            'picture_url.required_without_all' => 'Add a media link or upload a file.',
        ];
    }
}
