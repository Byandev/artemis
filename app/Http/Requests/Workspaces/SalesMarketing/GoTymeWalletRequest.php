<?php

namespace App\Http\Requests\Workspaces\SalesMarketing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Enums\WalletType;

/**
 * A Go Tyme wallet, on the way in or on the way back. Create and edit take the
 * same fields, so they share this one request. `is_user_wallet` is stamped by
 * the controller, not accepted here — only that page may mint one.
 *
 * Authorization is handled by the controller/gate; the request only validates.
 */
class GoTymeWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'wallet_type' => ['required', Rule::enum(WalletType::class)],
            'opening_balance' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'currency' => strtoupper((string) $this->input('currency', 'PHP')),
        ]);
    }
}
