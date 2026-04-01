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
            'id' => 'required|integer|unique:pages,id',
            'shop_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'pos_token' => 'required|string|max:255',
            'botcake_token' => 'nullable|string|max:255',
            'pancake_token' => 'nullable|string',
            'infotxt_token' => 'nullable|string|max:255',
            'infotxt_user_id' => 'nullable|string|max:255',
            'parcel_journey_flow_id' => 'nullable|integer',
            'parcel_journey_custom_field_id' => 'nullable|integer',
            'parcel_journey_enabled' => 'boolean',
            'status' => 'nullable|in:active,inactive',
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
            'id.unique' => 'This page has already been added to a workspace.',
            'shop_id.required' => 'The shops ID is required.',
            'name.required' => 'The page name is required.',
            'pos_token.required' => 'The POS token is required.',
        ];
    }
}
