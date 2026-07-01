<?php

namespace Modules\Inventory\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Models\InventoryNotificationSetting;

class NotificationSettingsController extends Controller
{
    /**
     * Show the workspace's inventory Discord notification settings.
     */
    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->ensureMember($request, $workspace);

        $setting = InventoryNotificationSetting::forWorkspace($workspace->id);

        return Inertia::render('settings/notifications', [
            'workspace' => $workspace,
            'settings' => [
                'discord_webhook_url' => $setting->discord_webhook_url,
                'deliveries_enabled' => $setting->deliveries_enabled,
                'deliveries_send_at' => $setting->deliveries_send_at,
                'awaiting_enabled' => $setting->awaiting_enabled,
                'awaiting_send_at' => $setting->awaiting_send_at,
            ],
            // So the UI can note the fallback used when no webhook is set here.
            'hasEnvWebhookFallback' => filled(config('services.discord.inventory_webhook_url') ?: config('services.discord.webhook_url')),
        ]);
    }

    /**
     * Persist the workspace's inventory Discord notification settings.
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);

        $validated = $request->validate([
            'discord_webhook_url' => ['nullable', 'string', 'url', 'max:512'],
            'deliveries_enabled' => ['required', 'boolean'],
            'deliveries_send_at' => ['required', 'date_format:H:i'],
            'awaiting_enabled' => ['required', 'boolean'],
            'awaiting_send_at' => ['required', 'date_format:H:i'],
        ]);

        InventoryNotificationSetting::updateOrCreate(
            ['workspace_id' => $workspace->id],
            $validated,
        );

        return Redirect::route('notifications.edit', ['workspace' => $workspace->slug])
            ->with('status', 'notifications-updated');
    }

    /** Guard against editing a workspace the user is not a member of. */
    private function ensureMember(Request $request, Workspace $workspace): void
    {
        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists(),
            403,
        );
    }
}
