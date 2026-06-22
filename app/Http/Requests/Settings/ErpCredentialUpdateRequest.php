<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ErpCredentialUpdateRequest extends FormRequest
{
    /**
     * The route's `auth` middleware already guarantees an authenticated user;
     * workspace membership is enforced in the controller.
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
            'erp_username' => ['nullable', 'string', 'max:255'],
            // Optional on update so the saved password is left untouched when blank.
            'erp_password' => ['nullable', 'string', 'min:6', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'erp_username' => 'ERP username',
            'erp_password' => 'ERP password',
        ];
    }
}
