<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\RmoAutoTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
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
                'enable_auto_tag_status' => $workspace->rmoAutoTagStatusEnabled(),
                'auto_tag_status_map' => $workspace->rmoAutoTagStatusMap(),
            ],
            'parcel_statuses' => RmoAutoTag::PARCEL_STATUSES,
            'rmo_statuses' => RmoAutoTag::RMO_STATUSES,
            'default_auto_tag_map' => RmoAutoTag::DEFAULT_MAP,
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate([
            'enable_edit_previous_day' => ['required', 'boolean'],
            'enable_bulk_status_update' => ['required', 'boolean'],
            'enable_auto_tag_status' => ['sometimes', 'boolean'],
            'auto_tag_status_map' => ['sometimes', 'nullable', 'array'],
            'auto_tag_status_map.*' => ['nullable', 'string', Rule::in(RmoAutoTag::RMO_STATUSES)],
        ]);

        // The auto-tag fields are optional so a payload that predates them can
        // still save the other two switches without silently wiping the map.
        $autoTagEnabled = array_key_exists('enable_auto_tag_status', $data)
            ? $data['enable_auto_tag_status']
            : $workspace->rmoAutoTagStatusEnabled();

        // Unmapped parcel statuses arrive as null from the form; sanitizeMap
        // drops those along with anything not on the current status lists.
        $autoTagMap = array_key_exists('auto_tag_status_map', $data)
            ? RmoAutoTag::sanitizeMap($data['auto_tag_status_map'])
            : $workspace->rmoAutoTagStatusMap();

        $workspace->rmoSetting()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'enable_edit_previous_day' => $data['enable_edit_previous_day'],
                'enable_bulk_status_update' => $data['enable_bulk_status_update'],
                'enable_auto_tag_status' => $autoTagEnabled,
                'auto_tag_status_map' => $autoTagMap,
            ],
        );

        // Tagging is the scheduled command's job, but waiting up to an hour to
        // see the switch do anything reads as broken. Apply today's rows now,
        // through the same code path the command uses.
        if ($autoTagEnabled && $autoTagMap !== []) {
            RmoAutoTag::apply($workspace->fresh(), today()->toDateString());
        }

        return Redirect::route('rmo-settings.edit', ['workspace' => $workspace->slug])
            ->with('status', 'rmo-settings-updated');
    }
}
