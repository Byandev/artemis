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
            'description' => ['nullable', 'string', 'max:5000'],
            'script' => ['nullable', 'string'],
            'picture_file' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
            'picture_url' => ['nullable', 'string', 'max:2048'],
            'reference_link' => ['nullable', 'string', 'max:2048'],
            'ads_manager_link' => ['nullable', 'string', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:5000'],
            'headline' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
