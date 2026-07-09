<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Department;
use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
        /** @var Department $department */
        $department = $this->route('department');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->ignore($department->id)
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
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
