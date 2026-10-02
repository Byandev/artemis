<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Rules\DiscordWebhookUrl;
use App\Support\RmoAutoTag;
use App\Support\RmoExternalTeamSheet;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
    use AuthorizesRequests;

    public function edit(Request $request, Workspace $workspace): Response
    {
        $setting = $workspace->rmoSetting;

        // The page is reachable with "Manage RMO Settings"; the Discord block
        // needs its own permission on top, so it is rendered only when held.
        $canManageNotifications = $request->user()->can(
            Permission::ManageRmoNotifications->value,
            $workspace,
        );

        return Inertia::render('settings/rmo', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'settings' => [
                'enable_edit_previous_day' => $workspace->rmoEditPreviousDayEnabled(),
                'enable_bulk_status_update' => $workspace->rmoBulkStatusUpdateEnabled(),
                'enable_auto_tag_status' => $workspace->rmoAutoTagStatusEnabled(),
                'enable_external_team_sync' => (bool) ($setting->enable_external_team_sync ?? false),
                'external_team_sheet_url' => $setting->external_team_sheet_url ?? null,
                'external_team_status_map' => RmoExternalTeamSheet::subStatusMap($workspace),
                'discord_daily_stats_enabled' => (bool) ($setting->discord_daily_stats_enabled ?? false),
                'discord_webhook_url' => $setting->discord_webhook_url ?? null,
                'discord_send_at' => $setting->discord_send_at ?? '18:00',
            ],
            'canManageNotifications' => $canManageNotifications,
            // Options for the external-team status pickers.
            'cxStatuses' => $workspace->rmoCxStatuses()->get(['id', 'name']),
            'riderStatuses' => $workspace->rmoRiderStatuses()->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate([
            'enable_edit_previous_day' => ['required', 'boolean'],
            'enable_bulk_status_update' => ['required', 'boolean'],
            'enable_auto_tag_status' => ['sometimes', 'boolean'],
            'enable_external_team_sync' => ['sometimes', 'boolean'],
            // A sheet link is what n8n reads, so the switch can't go on without one.
            'external_team_sheet_url' => [
                'nullable',
                'required_if_accepted:enable_external_team_sync',
                'string',
                'max:512',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (filled($value) && RmoExternalTeamSheet::sheetId($value) === null) {
                        $fail('Enter a Google Sheets link (https://docs.google.com/spreadsheets/d/…).');
                    }
                },
            ],
            // Sheet status => "cx:{id}" / "rider:{id}", or empty to tag nothing.
            'external_team_status_map' => ['sometimes', 'array:'.implode(',', array_keys(RmoExternalTeamSheet::STATUS_MAP))],
            'external_team_status_map.*' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($workspace) {
                    [$type, $id] = array_pad(explode(':', (string) $value, 2), 2, null);
                    $relation = ['cx' => 'rmoCxStatuses', 'rider' => 'rmoRiderStatuses'][$type] ?? null;

                    if (! $relation || ! ctype_digit((string) $id) || ! $workspace->{$relation}()->whereKey($id)->exists()) {
                        $fail('Pick one of this workspace\'s customer or rider statuses.');
                    }
                },
            ],
            'discord_daily_stats_enabled' => ['sometimes', 'boolean'],
            'discord_webhook_url' => ['nullable', 'string', 'max:512', new DiscordWebhookUrl],
            // Whole-hour send times only (HH:00) — the scheduler runs hourly,
            // so any other minute would never match and silently never send.
            'discord_send_at' => ['sometimes', 'date_format:H:i', 'regex:/^\d{2}:00$/'],
        ]);

        // Optional in the payload so a client that predates auto-tagging can
        // still save the other two switches without silently flipping it off.
        $autoTagEnabled = array_key_exists('enable_auto_tag_status', $data)
            ? $data['enable_auto_tag_status']
            : $workspace->rmoAutoTagStatusEnabled();

        $attributes = [
            'enable_edit_previous_day' => $data['enable_edit_previous_day'],
            'enable_bulk_status_update' => $data['enable_bulk_status_update'],
            'enable_auto_tag_status' => $autoTagEnabled,
        ];

        // Same "optional in the payload" rule as auto-tag above.
        if (array_key_exists('enable_external_team_sync', $data)) {
            $attributes['enable_external_team_sync'] = $data['enable_external_team_sync'];
            $attributes['external_team_sheet_url'] = $data['external_team_sheet_url'] ?? null;
        }

        if (array_key_exists('external_team_status_map', $data)) {
            // Every sheet status stored explicitly, so a blank pick stays
            // "tag nothing" rather than falling back to the default again.
            $attributes['external_team_status_map'] = array_map(
                fn ($sheetStatus) => (string) ($data['external_team_status_map'][$sheetStatus] ?? ''),
                array_combine(array_keys(RmoExternalTeamSheet::STATUS_MAP), array_keys(RmoExternalTeamSheet::STATUS_MAP)),
            );
        }

        // Only written by someone who holds the notification permission. A
        // payload that carries these fields without it is ignored rather than
        // rejected, so the other switches still save.
        if ($request->user()->can(Permission::ManageRmoNotifications->value, $workspace)) {
            $attributes += array_intersect_key($data, array_flip([
                'discord_daily_stats_enabled',
                'discord_webhook_url',
                'discord_send_at',
            ]));
        }

        $workspace->rmoSetting()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            $attributes,
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
