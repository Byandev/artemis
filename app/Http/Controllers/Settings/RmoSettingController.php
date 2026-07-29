<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workspace-level RMO management settings. Reaching either action requires the
 * "Manage RMO Settings" permission, applied as route middleware.
 */
class RmoSettingController extends Controller
{
    public function edit(Request $request, Workspace $workspace): Response
    {
        return Inertia::render('settings/rmo', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'settings' => [
                'enable_edit_previous_day' => $workspace->rmoEditPreviousDayEnabled(),
                'enable_bulk_status_update' => $workspace->rmoBulkStatusUpdateEnabled(),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate([
            'enable_edit_previous_day' => ['required', 'boolean'],
            'enable_bulk_status_update' => ['required', 'boolean'],
        ]);

        $workspace->rmoSetting()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'enable_edit_previous_day' => $data['enable_edit_previous_day'],
                'enable_bulk_status_update' => $data['enable_bulk_status_update'],
            ],
        );

        return Redirect::route('rmo-settings.edit', ['workspace' => $workspace->slug])
            ->with('status', 'rmo-settings-updated');
    }
}
