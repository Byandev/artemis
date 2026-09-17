<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Rules\DiscordWebhookUrl;
use App\Support\RmoAutoAssign;
use App\Support\RmoAutoTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
                'enable_auto_assign' => $workspace->rmoAutoAssignEnabled(),
                // The effective pool, not the raw column: a CSR who has since
                // left the workspace is already ignored when orders are handed
                // out, so showing them here would promise something that does
                // not happen — and saving them back would fail validation.
                // Dropping them from the picker cleans the column up on save.
                'auto_assign_user_ids' => RmoAutoAssign::pool($workspace),
                'discord_daily_stats_enabled' => (bool) ($setting->discord_daily_stats_enabled ?? false),
                'discord_webhook_url' => $setting->discord_webhook_url ?? null,
                'discord_send_at' => $setting->discord_send_at ?? '18:00',
            ],
            'assignableUsers' => RmoAutoAssign::assignableUsers($workspace),
            'canManageNotifications' => $canManageNotifications,
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        // The pool may only name CSRs this workspace could assign by hand —
        // anything else is rejected rather than stored and quietly ignored.
        $assignableIds = RmoAutoAssign::assignableUsers($workspace)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $data = $request->validate([
            'enable_edit_previous_day' => ['required', 'boolean'],
            'enable_bulk_status_update' => ['required', 'boolean'],
            'enable_auto_tag_status' => ['sometimes', 'boolean'],
            'enable_auto_assign' => ['sometimes', 'boolean'],
            'auto_assign_user_ids' => ['sometimes', 'array'],
            'auto_assign_user_ids.*' => ['string', Rule::in($assignableIds)],
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

        // Same treatment for auto-assignment: absent from the payload means
        // "leave it as it was", not "turn it off".
        $autoAssignEnabled = array_key_exists('enable_auto_assign', $data)
            ? $data['enable_auto_assign']
            : $workspace->rmoAutoAssignEnabled();

        $attributes = [
            'enable_edit_previous_day' => $data['enable_edit_previous_day'],
            'enable_bulk_status_update' => $data['enable_bulk_status_update'],
            'enable_auto_tag_status' => $autoTagEnabled,
            'enable_auto_assign' => $autoAssignEnabled,
        ];

        if (array_key_exists('auto_assign_user_ids', $data)) {
            $attributes['auto_assign_user_ids'] = array_values(array_unique(
                array_map('strval', $data['auto_assign_user_ids']),
            ));
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

        // Likewise for auto-assignment: hand out today's unassigned rows now, so
        // turning the switch on shows its effect on the RMO page immediately
        // rather than after the next sync or the nightly sweep.
        if ($autoAssignEnabled) {
            RmoAutoAssign::apply($workspace->fresh(), today()->toDateString());
        }

        return Redirect::route('rmo-settings.edit', ['workspace' => $workspace->slug])
            ->with('status', 'rmo-settings-updated');
    }
}
