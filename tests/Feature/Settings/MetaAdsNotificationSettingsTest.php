<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\MetaAds\Models\NotificationSetting;

/** Valid settings payload; individual fields are overridden per test. */
function metaAdsNotificationPayload(array $overrides = []): array
{
    return array_merge([
        'inactive_accounts_enabled' => true,
        'inactive_accounts_webhook_url' => null,
        'inactive_accounts_send_at' => '09:00',
    ], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
});

function putMetaAdsSettings(array $overrides = [])
{
    return test()->actingAs(test()->user)->put(
        route('meta-ads-notifications.update', ['workspace' => test()->workspace->slug]),
        metaAdsNotificationPayload($overrides),
    );
}

it('renders the settings page for a workspace owner', function () {
    $this->actingAs($this->user)
        ->get(route('meta-ads-notifications.edit', ['workspace' => $this->workspace->slug]))
        ->assertOk();
});

it('saves the webhook, hour and enabled flag', function () {
    $url = 'https://discord.com/api/webhooks/123456789012345678/aBcDeF-gHiJkLmNoPqRsTuVwXyZ';

    putMetaAdsSettings([
        'inactive_accounts_webhook_url' => $url,
        'inactive_accounts_send_at' => '17:00',
    ])->assertSessionHasNoErrors();

    $setting = NotificationSetting::forWorkspace($this->workspace->id);

    expect($setting->inactive_accounts_webhook_url)->toBe($url)
        ->and($setting->inactive_accounts_send_at)->toBe('17:00')
        ->and($setting->inactive_accounts_enabled)->toBeTrue();
});

it('rejects a URL that is not a discord webhook', function (string $url) {
    putMetaAdsSettings(['inactive_accounts_webhook_url' => $url])
        ->assertSessionHasErrors('inactive_accounts_webhook_url');

    expect(NotificationSetting::where('workspace_id', $this->workspace->id)->exists())->toBeFalse();
})->with([
    'unrelated host' => 'https://google.com',
    'webhook path on another host' => 'https://example.com/api/webhooks/123/tok',
    'discord but not a webhook' => 'https://discord.com/channels/123/456',
    'plain text' => 'not-a-url',
]);

// The report command runs hourly, so a send time with a non-zero minute would
// never match the current HH:MM and the report would silently never send.
it('rejects a send time that is not a whole hour', function (string $time) {
    putMetaAdsSettings(['inactive_accounts_send_at' => $time])
        ->assertSessionHasErrors('inactive_accounts_send_at');
})->with(['09:30', '09:05', 'noon']);

it('blocks a user who is not a member of the workspace', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('meta-ads-notifications.edit', ['workspace' => $this->workspace->slug]))
        ->assertForbidden();
});
