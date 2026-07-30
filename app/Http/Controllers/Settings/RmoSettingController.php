<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\RmoAutoTag;
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
                'enable_auto_tag_status' => $workspace->rmoAutoTagStatusEnabled(),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate([
            'enable_edit_previous_day' => ['required', 'boolean'],
            'enable_bulk_status_update' => ['required', 'boolean'],
            'enable_auto_tag_status' => ['sometimes', 'boolean'],
        ]);

        // Optional in the payload so a client that predates auto-tagging can
        // still save the other two switches without silently flipping it off.
        $autoTagEnabled = array_key_exists('enable_auto_tag_status', $data)
            ? $data['enable_auto_tag_status']
            : $workspace->rmoAutoTagStatusEnabled();

        $workspace->rmoSetting()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'enable_edit_previous_day' => $data['enable_edit_previous_day'],
                'enable_bulk_status_update' => $data['enable_bulk_status_update'],
                'enable_auto_tag_status' => $autoTagEnabled,
            ],
        );

        // Tagging is the nightly command's job, but waiting until midnight to
        // see the switch do anything reads as broken. Apply today's rows now,
        // through the same code path the command uses.
        if ($autoTagEnabled) {
            RmoAutoTag::apply($workspace->fresh(), today()->toDateString());
        }

        return Redirect::route('rmo-settings.edit', ['workspace' => $workspace->slug])
            ->with('status', 'rmo-settings-updated');
    }
}
