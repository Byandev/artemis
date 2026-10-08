<?php

namespace Modules\TaskManagement\Http\Requests;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Enums\TaskStatusType;
use Modules\TaskManagement\Models\Space;

class StoreTaskStatusRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', $this->uniqueSlugRule($space)],
            'type' => ['required', Rule::enum(TaskStatusType::class)],
            'color' => ['nullable', 'string', 'max:32'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Reject a name that would collide with an existing status of the space.
     */
    private function uniqueSlugRule(Space $space): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($space): void {
            if ($space->statuses()->where('slug', Str::slug((string) $value))->exists()) {
                $fail(__('A status with this name already exists in this space.'));
            }
        };
    }
}
