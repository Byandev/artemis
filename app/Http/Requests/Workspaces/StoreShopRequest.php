<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShopRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => [
                'required',
                'integer',
                // A shop row is keyed by its POS shop id, so the same shop can only
                // ever be connected once across the whole app — the check is global,
                // not per-workspace. Catching it here means the client gets an inline
                // field error before the controller makes any POS API call or hits a
                // duplicate-key error mid-request.
                'unique:shops,id',
            ],
            'pos_token' => 'required|string|max:255',
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
            'shop_id.required' => 'The shop ID is required.',
            // The shop may be held by this workspace or another one; the rule cannot
            // tell them apart, so the message stays true for both.
            'shop_id.unique' => 'This shop is already connected. Remove it from the workspace that has it before adding it here.',
            'pos_token.required' => 'The POS token is required.',
        ];
    }
}
