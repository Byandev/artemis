<?php

namespace Modules\Creatives\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Creatives\Models\Creative;

class UpdateCreativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');
        $workspaceId = $workspace instanceof Workspace ? $workspace->getKey() : $workspace;

        $creative = $this->route('creative');
        $creativeId = $creative instanceof Creative ? $creative->getKey() : $creative;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                // Unique per workspace, ignoring the creative being updated.
                Rule::unique('creatives', 'name')
                    ->where('workspace_id', $workspaceId)
                    ->ignore($creativeId),
            ],
            'creative_date' => ['sometimes', 'required', 'date'],
            'format' => ['sometimes', 'required', Rule::in(['video', 'image'])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'assigned_reviewer_ids' => ['sometimes', 'nullable', 'array'],
            'assigned_reviewer_ids.*' => ['integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'script' => ['sometimes', 'nullable', 'string'],
            // Media is either a link (e.g. Google Drive) or a file uploaded
            // into Artemis. withValidator() checks at least one survives.
            'picture_url' => [
                'sometimes', 'nullable', 'string', 'max:2048',
                Rule::unique('creatives', 'picture_url')->where('workspace_id', $workspaceId)->ignore($creativeId),
            ],
            'media_key' => ['nullable', 'string'],
            // The browser's file name, kept for display and download.
            'media_name' => ['nullable', 'string', 'max:255'],
            'media_file' => ['nullable', 'file', 'mimetypes:image/*,video/*'],
            // Set when the user clears the uploaded file without picking a replacement.
            'remove_media' => ['nullable', 'boolean'],
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

    /**
     * A creative must keep either a media link or an uploaded file. Only
     * checked when the request touches media, so partial updates (the inline
     * status dropdowns) are unaffected.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('picture_url') && ! $this->boolean('remove_media')) {
                return;
            }

            $creative = $this->route('creative');

            $hasLink = $this->has('picture_url')
                ? filled($this->input('picture_url'))
                : filled($creative?->picture_url);

            $hasFile = filled($this->input('media_key'))
                || $this->hasFile('media_file')
                || ($creative?->mediaFile() && ! $this->boolean('remove_media'));

            if (! $hasLink && ! $hasFile) {
                $validator->errors()->add('picture_url', 'Add a media link or upload a file.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A creative with this name already exists in this workspace.',
            'picture_url.unique' => 'A creative with this media link already exists in this workspace.',
        ];
    }
}
