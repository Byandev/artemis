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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'script' => ['required', 'string'],
            // Media is a plain text link (e.g. Google Drive), not an uploaded image.
            'picture_url' => ['required', 'string', 'max:2048'],
            'reference_link' => ['nullable', 'string', 'max:2048'],
            'caption' => ['required', 'string', 'max:5000'],
            'headline' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
