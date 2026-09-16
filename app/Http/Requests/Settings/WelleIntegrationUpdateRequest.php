<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'welle_email' => ['required', 'email', 'max:255'],
            // Always required, unlike before: the password is not stored, so
            // there is nothing to leave untouched. Reconnecting means proving
            // the account again and taking a fresh token.
            'welle_password' => ['required', 'string', 'min:6', 'max:255'],
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
