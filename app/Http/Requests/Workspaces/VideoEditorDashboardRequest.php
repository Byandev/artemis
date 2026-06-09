<?php

namespace App\Http\Requests\Workspaces;

use App\Enums\Permission;
use App\Models\Workspace;
use App\Support\VideoEditor\DashboardFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared request for the video-editor dashboard (Inertia shell + per-section
 * API). Centralises authorization, input validation, and filter parsing so
 * the controllers stay thin.
 */
class VideoEditorDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && $this->user()->can(Permission::ViewVideoEditorDashboard->value, $workspace);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
            'formats' => ['nullable', 'array'],
            'formats.*' => ['in:video,image'],
            'group' => ['nullable', 'in:daily,weekly,monthly,yearly'],
        ];
    }

    public function filters(): DashboardFilters
    {
        return DashboardFilters::fromRequest($this);
    }
}
