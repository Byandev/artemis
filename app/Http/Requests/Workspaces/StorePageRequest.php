<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'required|integer',
            'shop_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'pos_token' => 'required|string|max:255',
            'botcake_token' => 'nullable|string|max:255',
            'infotxt_token' => 'nullable|string|max:255',
            'infotxt_user_id' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => 'The page ID is required.',
            'shop_id.required' => 'The shop ID is required.',
            'name.required' => 'The page name is required.',
            'pos_token.required' => 'The POS token is required.',
        ];
    }
}
