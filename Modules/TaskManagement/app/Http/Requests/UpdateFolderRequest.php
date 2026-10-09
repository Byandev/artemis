<?php

namespace Modules\TaskManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Models\Folder;

class UpdateFolderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('folder'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes', 'string', 'max:10', 'regex:/^[A-Z][A-Z0-9]*$/',
                Rule::notIn([Folder::UNASSIGNED_CODE]),
                Rule::unique('task_folders', 'code')
                    ->where('workspace_id', $this->folder()->workspace_id)
                    ->ignore($this->route('folder')),
                Rule::unique('tasks', 'ticket_code')->where(
                    fn (Builder $query) => $query
                        ->where('workspace_id', $this->folder()->workspace_id)
                        ->whereNot('ticket_code', $this->folder()->code),
                ),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The folder being updated.
     */
    private function folder(): Folder
    {
        $folder = $this->route('folder');

        return $folder instanceof Folder
            ? $folder
            : Folder::query()->whereKey($folder)->firstOrFail();
    }

    /**
     * Upper-case a supplied code so the stored value and the unique check agree
     * however the user typed it. Renaming the folder never re-derives the code.
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
            'code.unique' => 'That project code is already in use, or has already numbered tickets. Choose another one.',
            'code.not_in' => 'That project code is reserved. Choose another one.',
            'code.regex' => 'A project code is letters and digits, starting with a letter.',
        ];
    }
}
