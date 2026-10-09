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
use Modules\TaskManagement\Models\TaskStatus;

class UpdateTaskStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('status'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TaskStatus $status */
        $status = $this->route('status');

        return [
            'name' => ['sometimes', 'string', 'max:255', $this->uniqueSlugRule($status)],
            'type' => ['sometimes', Rule::enum(TaskStatusType::class)],
            'color' => ['nullable', 'string', 'max:32'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Reject a name that would collide with another status of the same space.
     */
    private function uniqueSlugRule(TaskStatus $status): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($status): void {
            $exists = $status->space->statuses()
                ->whereKeyNot($status->id)
                ->where('slug', Str::slug((string) $value))
                ->exists();

            if ($exists) {
                $fail(__('A status with this name already exists in this space.'));
            }
        };
    }
}
