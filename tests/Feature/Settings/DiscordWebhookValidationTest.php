<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Inventory\Models\InventoryNotificationSetting;

/** Valid settings payload; the webhook fields are overridden per test. */
function notificationPayload(array $overrides = []): array
{
    return array_merge([
        'deliveries_webhook_url' => null,
        'awaiting_webhook_url' => null,
        'deliveries_enabled' => true,
        'deliveries_send_at' => '09:00',
        'awaiting_enabled' => true,
        'awaiting_send_at' => '10:00',
    ], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'discord_notifications_module_enabled' => true,
    ]);
});

it('rejects a URL that is not a discord webhook', function (string $url) {
    $this->actingAs($this->user)
        ->put(
            route('notifications.update', ['workspace' => $this->workspace->slug]),
            notificationPayload(['deliveries_webhook_url' => $url]),
        )
        ->assertSessionHasErrors('deliveries_webhook_url');

    expect(InventoryNotificationSetting::where('workspace_id', $this->workspace->id)->exists())
        ->toBeFalse();
})->with([
    'unrelated host' => 'https://google.com',
    'webhook path on another host' => 'https://example.com/api/webhooks/123/tok',
    'discord but not a webhook' => 'https://discord.com/channels/123/456',
    'lookalike host' => 'https://discord.com.evil.com/api/webhooks/1/tok',
    'plain text' => 'not-a-url',
]);

it('accepts a real discord webhook url', function () {
    $url = 'https://discord.com/api/webhooks/123456789012345678/aBcDeF-gHiJkLmNoPqRsTuVwXyZ';

    $this->actingAs($this->user)
        ->put(
            route('notifications.update', ['workspace' => $this->workspace->slug]),
            notificationPayload(['deliveries_webhook_url' => $url]),
        )
        ->assertSessionHasNoErrors();

    expect(InventoryNotificationSetting::forWorkspace($this->workspace->id)->deliveries_webhook_url)
        ->toBe($url);
});

it('still allows blank webhooks so the env fallback can be used', function () {
    $this->actingAs($this->user)
        ->put(
            route('notifications.update', ['workspace' => $this->workspace->slug]),
            notificationPayload(),
        )
        ->assertSessionHasNoErrors();

    expect(InventoryNotificationSetting::forWorkspace($this->workspace->id)->deliveries_webhook_url)
        ->toBeNull();
});
