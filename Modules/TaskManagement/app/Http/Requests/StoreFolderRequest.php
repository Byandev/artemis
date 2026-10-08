<?php

namespace Modules\TaskManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Support\TicketCodes;

class StoreFolderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('manageStructure', $this->route('space'));
    }

    /**
     * Fill in the code the name derives to when the user did not choose one,
     * moving to the first free variant (ART2, ART3) when it is taken.
     *
     * Doing it here rather than in the controller means a derived code that is
     * already taken fails the same unique rule a typed one would, so the user is
     * told which field to fix and the folder is not saved -- rather than being
     * silently given a code that is not the one it appears to have.
     */
    protected function prepareForValidation(): void
    {
        $code = $this->string('code')->trim()->toString();
        $name = $this->string('name')->trim()->toString();

        if ($code === '' && $name !== '') {
            /** @var Space $space */
            $space = $this->route('space');
            $code = TicketCodes::firstFreeFor($space->workspace_id, $name);
        }

        if ($code !== '') {
            $this->merge(['code' => mb_strtoupper($code)]);
        }
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

        // Codes are unique per Artemis workspace, not across every workspace.
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:10', 'regex:/^[A-Z][A-Z0-9]*$/',
                TicketCodes::availableRule($space->workspace_id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'A project code is letters and digits, starting with a letter.',
        ];
    }
}
