<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $page = $this->route('page');
        $workspace = $this->route('workspace');

        // Ensure the page belongs to the workspace
        return $page->workspace_id === $workspace->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => 'required|integer|exists:shops,id',
            'name' => 'required|string|max:255',
            'facebook_url' => 'nullable|url|max:500',
            'pos_token' => 'nullable|string|max:255',
            'botcake_token' => 'nullable|string|max:255',
            'infotxt_token' => 'nullable|string|max:255',
            'infotxt_user_id' => 'nullable|string|max:255',
            'pancake_token' => 'nullable|string',
            'parcel_journey_flow_id' => 'required|integer',
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
            'shop_id.required' => 'The shop is required.',
            'shop_id.exists' => 'The selected shop does not exist.',
            'name.required' => 'The page name is required.',
            'facebook_url.url' => 'The Facebook URL must be a valid URL.',
        ];
    }
}
