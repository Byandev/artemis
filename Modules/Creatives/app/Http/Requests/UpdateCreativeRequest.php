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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'creative_date' => ['sometimes', 'required', 'date'],
            'format' => ['sometimes', 'required', Rule::in(['video', 'image'])],
            'assigned_reviewer_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'script' => ['nullable', 'string'],
            // Media is a plain text link (e.g. Google Drive), not an uploaded image.
            'picture_url' => ['nullable', 'string', 'max:2048'],
            'reference_link' => ['nullable', 'string', 'max:2048'],
            'ads_status' => ['nullable', Rule::in(['pending', 'running', 'kill', 'skill'])],
            'ads_manager_link' => ['nullable', 'string', 'max:2048'],
            'ads_remarks' => ['nullable', 'string', 'max:2000'],
            'final_status' => ['sometimes', Rule::in(['for_approval', 'approved', 'for_revision'])],
            'caption' => ['nullable', 'string', 'max:5000'],
            'headline' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
