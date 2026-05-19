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

        $allowedMetrics = array_values($validated['allowed_metrics']);
        $defaultMetrics = array_values(
            array_intersect(
                $validated['default_metrics'] ?? [],
                $allowedMetrics
            )
        );

        if ($defaultMetrics === []) {
            $defaultMetrics = array_values(
                array_intersect(MetricRegistry::defaults(), $allowedMetrics)
            );
        }

        $dataToUpdate = [
            'allowed_metrics' => $allowedMetrics,
            'default_metrics' => $defaultMetrics,
        ];

        $workspace->metricSetting()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            $dataToUpdate
        );

        return back()->with('success', 'Metric settings updated.');
    }
}
