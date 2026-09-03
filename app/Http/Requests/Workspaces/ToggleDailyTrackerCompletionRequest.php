<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleDailyTrackerCompletionRequest extends FormRequest
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
            // Scoped to the workspace so an id from another workspace reads as a
            // validation failure rather than a silent cross-tenant write.
            'item_id' => [
                'required',
                'integer',
                Rule::exists('daily_tracker_items', 'id')
                    ->where('workspace_id', $workspace->id)
                    ->where('active', true),
            ],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('workspace_user', 'user_id')
                    ->where('workspace_id', $workspace->id),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'completed' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_id.exists' => 'That deliverable is no longer on this tracker.',
            'user_id.exists' => 'That member is not part of this workspace.',
        ];
    }
}
