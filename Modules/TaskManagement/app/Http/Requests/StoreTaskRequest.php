<?php

namespace Modules\TaskManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Http\Requests\Concerns\ValidatesTaskAttributes;
use Modules\TaskManagement\Models\TaskList;

class StoreTaskRequest extends FormRequest
{
    use ValidatesTaskAttributes;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('manageTasks', $this->route('list'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TaskList $list */
        $list = $this->route('list');

        return [
            'name' => ['required', 'string', 'max:255'],
            ...$this->taskAttributeRules($list->space, $list),
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->taskAttributeMessages();
    }
}
