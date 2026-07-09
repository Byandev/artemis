<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    /**
     * Authorization is handled by the controller/gate; the request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Workspace $workspace */
        $workspace = $this->route('workspace');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            // The manager must be a member of the current workspace.
            'manager_id' => [
                'nullable',
                Rule::exists('workspace_user', 'user_id')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A department with this name already exists in this workspace.',
            'manager_id.exists' => 'The selected manager is not a member of this workspace.',
        ];
    }
}
