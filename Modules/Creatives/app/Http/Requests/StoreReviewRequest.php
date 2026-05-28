<?php

namespace Modules\Creatives\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'waiting_for_submission',
                'for_approval',
                'revision',
                'approved',
                'for_reapproval',
            ])],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
