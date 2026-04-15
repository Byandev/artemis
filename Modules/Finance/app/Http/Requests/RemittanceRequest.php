<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'courier' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'deductions' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['pending', 'remitted'])],
        ];
    }
}
