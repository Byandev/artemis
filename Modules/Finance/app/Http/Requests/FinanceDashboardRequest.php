<?php

namespace Modules\Finance\Http\Requests;

use App\Enums\Permission;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared authorization, validation, and date-range parsing for every finance
 * dashboard stat endpoint — so each controller method stays a one-liner and
 * every widget applies the same [start, end] window.
 */
class FinanceDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && (bool) $this->user()?->can(Permission::ViewFinanceDashboard->value, $workspace);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'end' => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'after_or_equal:start'],
        ];
    }

    /**
     * The [start, end] window (either may be null — the query fills defaults).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function range(): array
    {
        return [$this->input('start'), $this->input('end')];
    }
}
