<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $workspace = $this->route('workspace');

        return [
            'shop_id' => [
                'required',
                'integer',
                // Reject a shop already in this workspace up front, before the
                // controller makes any POS API call — the client gets an inline
                // field error instead of an HTTP error thrown mid-request.
                Rule::unique('shops', 'id')->where(fn ($query) => $query->where('workspace_id', $workspace?->id)),
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
            'shop_id.unique' => 'This shop has already been added to this workspace.',
            'pos_token.required' => 'The POS token is required.',
        ];
    }
}
