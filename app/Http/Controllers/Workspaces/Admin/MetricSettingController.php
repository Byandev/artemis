<?php

namespace App\Http\Controllers\Workspaces\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\Metrics\MetricRegistry;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MetricSettingController extends Controller
{
    use AuthorizesRequests;

    // public function edit(Workspace $workspace)
    // {
    //     $this->authorize('manage', $workspace);

    //     return inertia('workspaces/admin/metric-settings/index', [
    //         'workspace' => $workspace,
    //         'settings' => $workspace->metricSetting,
    //         'configs' => MetricRegistry::configs(),
    //     ]);
    // }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize('manage', $workspace);

        $validKeys = MetricRegistry::all();

        $validated = $request->validate([
            'allowed_metrics' => ['required', 'array', 'min:1'],
            'allowed_metrics.*' => [Rule::in($validKeys)],

            'default_metrics' => ['nullable', 'array'],
            'default_metrics.*' => [Rule::in($validKeys)],
        ]);

        $dataToUpdate = [
            'allowed_metrics' => array_values($validated['allowed_metrics']),
            'default_metrics' => array_values($validated['default_metrics'] ?? $validated['allowed_metrics']),
        ];

        $workspace->metricSetting()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            $dataToUpdate
        );

        return back()->with('success', 'Metric settings updated.');
    }
}
