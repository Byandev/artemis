<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\ShippingAddress;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * The customer lookup: one identifier in, everything known about them out.
 */
function customerOrder($workspace, string $customerId, string $phone, string $name = 'Remie Malolot'): Order
{
    $order = Order::factory()->forWorkspace($workspace)->create([
        'customer_id' => $customerId,
        'confirmed_at' => '2026-07-20 09:00:00',
    ]);

    ShippingAddress::factory()->create([
        'order_id' => $order->id,
        'phone_number' => $phone,
        'full_name' => $name,
        'full_address' => 'Purok 3, Danao, Panglao, Bohol',
    ]);

    return $order;
}

test('a customer id shows the name, phone, address and orders', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $customerId = '3903459b-8247-416a-80c1-6825901075de';
    $order = customerOrder($workspace, $customerId, '09154262216');

    $this->artisan('pancake:customer', ['identifier' => $customerId])
        ->expectsOutputToContain('Remie Malolot')
        ->expectsOutputToContain('09154262216')
        ->expectsOutputToContain('Purok 3, Danao, Panglao, Bohol')
        ->expectsOutputToContain((string) $order->order_number)
        ->assertSuccessful();
});

test('a phone number finds the same customer, however it is spelled', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    customerOrder($workspace, fake()->uuid(), '09154262216');

    $this->artisan('pancake:customer', ['identifier' => '+639154262216'])
        ->expectsOutputToContain('Remie Malolot')
        ->assertSuccessful();
});

test('an order number finds the customer behind it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = customerOrder($workspace, fake()->uuid(), '09154262216');

    $this->artisan('pancake:customer', ['identifier' => $order->order_number])
        ->expectsOutputToContain('Remie Malolot')
        ->assertSuccessful();
});

test('a number that only the delivery row carries still finds the customer', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    // The address and the courier were given different numbers, which happens.
    $order = customerOrder($workspace, fake()->uuid(), '09475729296');

    OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09322569774',
        'rider_name' => 'Rider',
        'rider_phone' => '09108321158',
        'delivery_date' => '2026-07-20',
    ]);

    $this->artisan('pancake:customer', ['identifier' => '09322569774'])
        ->expectsOutputToContain('Remie Malolot')
        // Both numbers are listed — the one searched for is not hidden behind
        // the one on the address.
        ->expectsOutputToContain('09475729296, 09322569774')
        ->assertSuccessful();
});

test('calls placed to the delivery number are listed against the customer', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = customerOrder($workspace, fake()->uuid(), '09475729296');

    OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09322569774',
        'rider_name' => 'Rider',
        'rider_phone' => '09108321158',
        'delivery_date' => '2026-07-20',
    ]);

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'phone_number' => '09322569774',
        'call_date' => '2026-07-20',
        'call_time' => '11:24:44',
        'duration' => 10,
        'persona' => 'customer',
        'order_id' => $order->id,
    ]);

    $this->artisan('pancake:customer', ['identifier' => $order->order_number])
        ->expectsOutputToContain('Call logs (1)')
        ->expectsOutputToContain('11:24:44')
        ->assertSuccessful();
});

test("a rider's number says so rather than reporting nothing", function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = customerOrder($workspace, fake()->uuid(), '09475729296');

    OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09322569774',
        'rider_name' => 'Rider',
        'rider_phone' => '09108321158',
        'delivery_date' => '2026-07-20',
    ]);

    $this->artisan('pancake:customer', ['identifier' => '09108321158'])
        ->expectsOutputToContain("That number is a rider's")
        ->assertFailed();
});

test('an unknown identifier fails with the accepted forms', function () {
    makeWorkspaceWithOwner();

    $this->artisan('pancake:customer', ['identifier' => '09999999999'])
        ->expectsOutputToContain('Nothing found')
        ->expectsOutputToContain('Accepts a customer id')
        ->assertFailed();
});

test('workspace scoping excludes another workspace customer', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    $customerId = fake()->uuid();
    customerOrder($other, $customerId, '09154262216');

    $this->artisan('pancake:customer', ['identifier' => $customerId, '--workspace' => $workspace->slug])
        ->expectsOutputToContain('Nothing found')
        ->assertFailed();
});
