<?php

use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The per-shop "Auto-fill order address" switch on the Edit Shop dialog: it
 * needs a POS token, and the first time it goes on the shop gets the secret
 * Pancake's webhook sends back in X-Artemis-Secret.
 */
function updateShop(Shop $shop, array $data)
{
    return test()->put(route('workspaces.shops.update', [$shop->workspace, $shop]), [
        'name' => $shop->name,
        ...$data,
    ]);
}

beforeEach(function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);
    $this->workspace = $workspace;
    Http::fake();
});

it('turns on, creates a webhook secret once, and keeps it when turned off', function () {
    $shop = Shop::factory()->forWorkspace($this->workspace)->create(['pos_token' => 'pos-123']);
    expect($shop->fresh())->auto_fill_address->toBeFalse()->webhook_secret->toBeNull();

    updateShop($shop, ['auto_fill_address' => true])->assertRedirect()->assertSessionHasNoErrors();

    $secret = $shop->fresh()->webhook_secret;
    expect($shop->fresh()->auto_fill_address)->toBeTrue()
        ->and($secret)->toHaveLength(40);

    // Stored encrypted, not as the raw secret.
    expect(DB::table('shops')->where('id', $shop->id)->value('webhook_secret'))->not->toBe($secret);

    updateShop($shop, ['auto_fill_address' => false])->assertRedirect();
    updateShop($shop, ['auto_fill_address' => true])->assertRedirect();

    expect($shop->fresh()->webhook_secret)->toBe($secret);
});

it('will not turn on without a POS token', function () {
    $shop = Shop::factory()->forWorkspace($this->workspace)->create(['pos_token' => null]);

    updateShop($shop, ['auto_fill_address' => true])
        ->assertSessionHasErrors('auto_fill_address');

    expect($shop->fresh())->auto_fill_address->toBeFalse()->webhook_secret->toBeNull();
});

it('keeps the setting when the form does not send it', function () {
    $shop = Shop::factory()->forWorkspace($this->workspace)->create(['pos_token' => 'pos-123', 'auto_fill_address' => true]);

    updateShop($shop, ['name' => 'Renamed'])->assertRedirect();

    expect($shop->fresh())->name->toBe('Renamed')->auto_fill_address->toBeTrue();
});

it('shows the secret to shop editors on the shops list', function () {
    $shop = Shop::factory()->forWorkspace($this->workspace)->create(['pos_token' => 'pos-123']);
    updateShop($shop, ['auto_fill_address' => true]);

    $row = $this->get(route('workspaces.shops.index', $this->workspace))
        ->assertOk()
        ->viewData('page')['props']['pages']['data'][0];

    expect($row['webhook_secret'])->toBe($shop->fresh()->webhook_secret);
});
