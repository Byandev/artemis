<?php

namespace Modules\TaskManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Models\Space;

class StoreLabelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('manageStructure', $this->route('space'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Space $space */
        $space = $this->route('space');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('task_labels', 'name')->where('space_id', $space->id),
            ],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
