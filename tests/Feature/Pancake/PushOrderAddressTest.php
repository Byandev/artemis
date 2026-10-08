<?php

use App\Enums\Permission;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Modules\Pancake\Models\Commune;
use Modules\Pancake\Models\District;
use Modules\Pancake\Models\Province;

/**
 * "Update in Pancake" in the Get address popup: writes the reviewed commune
 * (and the district / province it belongs to) plus the address line to the
 * order in Pancake, then to our own copy.
 */
function pushableOrder(Workspace $workspace, ?string $posToken = 'pos-123'): Order
{
    $shop = Shop::factory()->forWorkspace($workspace)->create(['pos_token' => $posToken]);
    $order = Order::factory()->forWorkspace($workspace)->create(['shop_id' => $shop->id, 'order_number' => '9001']);

    ShippingAddress::factory()->create([
        'order_id' => $order->id,
        'full_name' => 'Juan Dela Cruz',
        'phone_number' => '09171234567',
        'commune_id' => null,
    ]);

    Province::create(['id' => '63_108', 'country_code' => 63, 'name' => 'Batangas']);
    District::create(['id' => '63_108_lipa', 'province_id' => '63_108', 'name' => 'Lipa-city']);
    Commune::create(['id' => '63_108_lipa_1', 'province_id' => '63_108', 'district_id' => '63_108_lipa', 'name' => 'Sabang']);

    return $order;
}

function pushAddress(Workspace $workspace, Order $order, array $body = [])
{
    return test()->postJson(route('workspaces.pancake.orders.push-address', [$workspace, $order->id]), [
        'commune_id' => '63_108_lipa_1',
        'address' => 'Purok 3, near the chapel',
        ...$body,
    ]);
}

it('writes the address to Pancake and to our copy', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);
    $order = pushableOrder($workspace);

    Http::fake(['pos.pages.fm/*' => Http::response(['success' => true])]);

    pushAddress($workspace, $order)->assertOk()->assertJsonPath('message', 'Address updated in Pancake.');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_contains($request->url(), "pos.pages.fm/api/v1/shops/{$order->shop_id}/orders/9001?api_key=pos-123")
        && $request['shipping_address'] == [
            'full_name' => 'Juan Dela Cruz',
            'phone_number' => '09171234567',
            'address' => 'Purok 3, near the chapel',
            'province_id' => '63_108',
            'district_id' => '63_108_lipa',
            'commune_id' => '63_108_lipa_1',
        ]);

    expect($order->shippingAddress()->first())
        ->commune_id->toBe('63_108_lipa_1')
        ->district_name->toBe('Lipa-city')
        ->full_address->toBe('Purok 3, near the chapel, Sabang, Lipa-city, Batangas');
});

it('reports a refusal and leaves our copy alone', function (int $status, array $body) {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);
    $order = pushableOrder($workspace);

    Http::fake(['pos.pages.fm/*' => Http::response($body, $status)]);

    pushAddress($workspace, $order)->assertStatus(422)->assertJsonPath('message', 'Pancake did not accept the update. Try again, or update the order in Pancake.');

    expect($order->shippingAddress()->first()->commune_id)->toBeNull();
})->with([
    'http error' => [422, ['message' => 'bad']],
    'success false' => [200, ['success' => false]],
]);

it('needs the shop POS token', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);
    $order = pushableOrder($workspace, posToken: null);
    Http::fake();

    pushAddress($workspace, $order)->assertStatus(422);
    Http::assertNothingSent();
});

it('rejects an unknown commune or an empty address', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);
    $order = pushableOrder($workspace);
    Http::fake();

    pushAddress($workspace, $order, ['commune_id' => 'nope'])->assertJsonValidationErrors('commune_id');
    pushAddress($workspace, $order, ['address' => ''])->assertJsonValidationErrors('address');
    Http::assertNothingSent();
});

it('needs the Update Order Address permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = User::factory()->create();
    $workspace->users()->attach($member->id);
    $this->actingAs($member);

    $order = pushableOrder($workspace);
    Http::fake();

    expect($member->can(Permission::UpdateOrderAddress->value, $workspace))->toBeFalse();
    pushAddress($workspace, $order)->assertForbidden();
    Http::assertNothingSent();
});
