<?php

namespace Modules\TaskManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Models\Label;

class UpdateLabelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('label'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Label $label */
        $label = $this->route('label');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('task_labels', 'name')->where('space_id', $label->space_id)->ignore($label),
            ],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
