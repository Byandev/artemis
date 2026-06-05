<?php

namespace Modules\Creatives\Http\Requests;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'creative_date' => ['required', 'date'],
            'format' => ['required', Rule::in(['video', 'image'])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'script' => ['nullable', 'string', 'required_if:format,video'],
            // Media is a plain text link (e.g. Google Drive), not an uploaded image.
            'picture_url' => ['nullable', 'string', 'max:2048'],
            'reference_link' => ['nullable', 'string', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:5000'],
            'headline' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'script.required_if' => 'The script is required for video creatives.',
        ];
    }
}
