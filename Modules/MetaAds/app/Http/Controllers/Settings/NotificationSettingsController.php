<?php

namespace Modules\MetaAds\Http\Controllers\Settings;

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
use Modules\MetaAds\Models\NotificationSetting;

class NotificationSettingsController extends Controller
{
    use AuthorizesRequests;

    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->ensureMember($request, $workspace);
        $this->authorize(Permission::ManageDiscordNotifications->value, $workspace);

        $setting = NotificationSetting::forWorkspace($workspace->id);

        return Inertia::render('settings/meta-ads-notifications', [
            'workspace' => $workspace,
            'settings' => [
                'inactive_accounts_enabled' => $setting->inactive_accounts_enabled,
                'inactive_accounts_webhook_url' => $setting->inactive_accounts_webhook_url,
                'inactive_accounts_send_at' => $setting->inactive_accounts_send_at,
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);
        $this->authorize(Permission::ManageDiscordNotifications->value, $workspace);

        $validated = $request->validate([
            'inactive_accounts_enabled' => ['required', 'boolean'],
            'inactive_accounts_webhook_url' => ['nullable', 'string', 'max:512', new DiscordWebhookUrl],
            // Whole-hour send times only (HH:00) — the scheduler runs hourly,
            // so a non-:00 minute would never match and silently never send.
            'inactive_accounts_send_at' => ['required', 'date_format:H:i', 'regex:/^\d{2}:00$/'],
        ]);

        NotificationSetting::updateOrCreate(
            ['workspace_id' => $workspace->id],
            $validated,
        );

        return Redirect::route('meta-ads-notifications.edit', ['workspace' => $workspace->slug])
            ->with('status', 'meta-ads-notifications-updated');
    }

    private function ensureMember(Request $request, Workspace $workspace): void
    {
        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists(),
            403,
        );
    }
}
