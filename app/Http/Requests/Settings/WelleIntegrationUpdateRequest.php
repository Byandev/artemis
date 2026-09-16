<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WelleIntegrationUpdateRequest extends FormRequest
{
    /**
     * The route's `auth` middleware already guarantees an authenticated user;
     * workspace membership and the module toggle are enforced in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $passwordSet = (bool) $this->user()?->hasWellePassword();

        return [
            'welle_email' => ['required', 'email', 'max:255'],
            // Required on first connect; optional afterwards so a blank field
            // leaves the stored password untouched.
            'welle_password' => [
                Rule::requiredIf(! $passwordSet),
                'nullable',
                'string',
                'min:6',
                'max:255',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'welle_email' => 'Welle email',
            'welle_password' => 'Welle password',
        ];
    }
}
