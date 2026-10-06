<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\OrderForDeliveryCxStatus;
use Modules\Pancake\Models\OrderForDeliveryRiderStatus;

/**
 * The "RMO Statuses" settings page: CRUD for the workspace's CX and rider statuses, shown alongside the main
 * status on the public RMO page. `{type}` is constrained to cx|rider in the
 * routes; both actions sit behind "Manage RMO Settings".
 */
class RmoStatusController extends Controller
{
    /** @var array<string, array{model: class-string<Model>, column: string}> */
    private const TYPES = [
        'cx' => ['model' => OrderForDeliveryCxStatus::class, 'column' => 'cx_status_id'],
        'rider' => ['model' => OrderForDeliveryRiderStatus::class, 'column' => 'rider_status_id'],
    ];

    public function index(Workspace $workspace): Response
    {
        return Inertia::render('settings/rmo-statuses', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'cxStatuses' => $workspace->rmoCxStatuses()->get(['id', 'name']),
            'riderStatuses' => $workspace->rmoRiderStatuses()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $type): RedirectResponse
    {
        $model = self::TYPES[$type]['model'];
        $data = $this->validated($request, $workspace, $model);

        $model::create(['workspace_id' => $workspace->id, 'name' => $data['name']]);

        return back()->with('status', 'rmo-status-saved');
    }

    public function update(Request $request, Workspace $workspace, string $type, int $status): RedirectResponse
    {
        $model = self::TYPES[$type]['model'];
        $record = $model::where('workspace_id', $workspace->id)->findOrFail($status);
        $data = $this->validated($request, $workspace, $model, $record->id);

        $record->update(['name' => $data['name']]);

        return back()->with('status', 'rmo-status-saved');
    }

    public function destroy(Workspace $workspace, string $type, int $status): RedirectResponse
    {
        ['model' => $model, 'column' => $column] = self::TYPES[$type];
        $record = $model::where('workspace_id', $workspace->id)->findOrFail($status);

        // No FK on the for-delivery table, so clear the orders tagged with it
        // ourselves rather than leave them pointing at a missing row.
        OrderForDelivery::where('workspace_id', $workspace->id)
            ->where($column, $record->id)
            ->update([$column => null]);

        $record->delete();

        return back()->with('status', 'rmo-status-deleted');
    }

    /**
     * @param  class-string<Model>  $model
     * @return array{name: string}
     */
    private function validated(Request $request, Workspace $workspace, string $model, ?int $ignoreId = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique((new $model)->getTable(), 'name')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($ignoreId),
            ],
        ]);
    }
}
