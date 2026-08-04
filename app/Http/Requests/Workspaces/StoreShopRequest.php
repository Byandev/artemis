<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Shop;
use Closure;
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
        $workspace = $this->route('workspace');

        return [
            'shop_id' => [
                'required',
                'integer',
                // A shop row is keyed by its POS shop id, so the same shop can only
                // ever be connected once across the whole app. Catch both cases here,
                // before the controller makes any POS API call — the client gets an
                // inline field error instead of a duplicate-key error mid-request.
                function (string $attribute, mixed $value, Closure $fail) use ($workspace) {
                    $ownerWorkspaceId = Shop::query()->whereKey($value)->value('workspace_id');

                    if ($ownerWorkspaceId === null) {
                        return;
                    }

                    $fail((int) $ownerWorkspaceId === (int) $workspace?->id
                        ? 'This shop has already been added to this workspace.'
                        : 'This shop is already connected to another workspace. Remove it there before adding it here.');
                },
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
            'pos_token.required' => 'The POS token is required.',
        ];
    }
}
