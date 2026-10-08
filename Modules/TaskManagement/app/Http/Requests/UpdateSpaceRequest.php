<?php

namespace Modules\TaskManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Support\TicketCodes;

class UpdateSpaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('space'));
    }

    /**
     * Upper-case a supplied code so the stored value and the uniqueness check
     * agree however it was typed. Renaming the space never re-derives it, so
     * tickets already issued keep their prefix.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper($this->string('code')->trim()->toString())]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'A space code is letters and digits, starting with a letter.',
        ];
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
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes', 'string', 'max:'.TicketCodes::MAX_LENGTH, 'regex:'.TicketCodes::PATTERN,
                TicketCodes::availableRule($space->workspace_id, $space),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:32'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }
}
