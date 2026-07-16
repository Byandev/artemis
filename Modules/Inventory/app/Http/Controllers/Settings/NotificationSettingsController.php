<?php

namespace Modules\Inventory\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Rules\DiscordWebhookUrl;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Models\InventoryNotificationSetting;

class NotificationSettingsController extends Controller
{
    use AuthorizesRequests;

    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->ensureMember($request, $workspace);
        $this->authorize(Permission::ManageDiscordNotifications->value, $workspace);

        $setting = InventoryNotificationSetting::forWorkspace($workspace->id);

        return Inertia::render('settings/notifications', [
            'workspace' => $workspace,
            'settings' => [
                'deliveries_webhook_url' => $setting->deliveries_webhook_url,
                'awaiting_webhook_url' => $setting->awaiting_webhook_url,
                'deliveries_enabled' => $setting->deliveries_enabled,
                'deliveries_send_at' => $setting->deliveries_send_at,
                'awaiting_enabled' => $setting->awaiting_enabled,
                'awaiting_send_at' => $setting->awaiting_send_at,
            ],
            'hasEnvWebhookFallback' => filled(config('services.discord.inventory_webhook_url') ?: config('services.discord.webhook_url')),
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);
        $this->authorize(Permission::ManageDiscordNotifications->value, $workspace);

        $validated = $request->validate([
            'deliveries_webhook_url' => ['nullable', 'string', 'max:512', new DiscordWebhookUrl],
            'awaiting_webhook_url' => ['nullable', 'string', 'max:512', new DiscordWebhookUrl],
            'deliveries_enabled' => ['required', 'boolean'],
            // Whole-hour send times only (HH:00) — the scheduler runs hourly,
            // so a non-:00 minute would never match and silently never send.
            'deliveries_send_at' => ['required', 'date_format:H:i', 'regex:/^\d{2}:00$/'],
            'awaiting_enabled' => ['required', 'boolean'],
            'awaiting_send_at' => ['required', 'date_format:H:i', 'regex:/^\d{2}:00$/'],
        ]);

        InventoryNotificationSetting::updateOrCreate(
            ['workspace_id' => $workspace->id],
            $validated,
        );

        return Redirect::route('notifications.edit', ['workspace' => $workspace->slug])
            ->with('status', 'notifications-updated');
    }

    private function ensureMember(Request $request, Workspace $workspace): void
    {
        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists(),
            403,
        );
    }
}
