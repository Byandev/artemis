<?php

use App\Http\Controllers\Workspaces\RTS\ForDeliveryController;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * Run the private upsell filter against a real query, the same way the
 * `filter[upsell]` AllowedFilter callback does.
 */
function rmoUpsell(mixed $value): array
{
    $query = OrderForDelivery::query();

    $method = new ReflectionMethod(ForDeliveryController::class, 'applyUpsellFilter');
    $method->invoke(
        (new ReflectionClass(ForDeliveryController::class))->newInstanceWithoutConstructor(),
        $query,
        $value,
    );

    return $query->pluck('id')->all();
}

/**
 * An RMO row whose Gencys stamp left `upsell_price` at the given value —
 * null standing for a row Gencys never answered for.
 */
function makeUpsellDelivery(Workspace $workspace, ?string $upsellPrice): OrderForDelivery
{
    $order = Order::create([
        'workspace_id' => $workspace->id,
        'shop_id' => 1,
        'customer_id' => (string) Str::uuid(),
        'order_number' => 'ORD-'.fake()->unique()->numberBetween(1, 999999),
        'status' => 2,
        'status_name' => 'shipped',
        'inserted_at' => now(),
    ]);

    return OrderForDelivery::create([
        'order_id' => $order->id,
        'workspace_id' => $workspace->id,
        'shop_id' => 1,
        'delivery_date' => now()->toDateString(),
        'rider_name' => fake()->name(),
        'rider_phone' => fake()->numerify('09#########'),
        'status' => 'PENDING',
        'upsell_price' => $upsellPrice,
    ]);
}

beforeEach(function () {
    $this->workspace = makeWorkspaceWithOwner()['workspace'];
});

it('keeps only orders carrying an upsell', function () {
    $withUpsell = makeUpsellDelivery($this->workspace, '499.00');
    makeUpsellDelivery($this->workspace, null);
    makeUpsellDelivery($this->workspace, '0.00');

    expect(rmoUpsell('with'))->toBe([$withUpsell->id]);
});

it('filters out orders carrying an upsell', function () {
    makeUpsellDelivery($this->workspace, '499.00');
    $unstamped = makeUpsellDelivery($this->workspace, null);
    $zero = makeUpsellDelivery($this->workspace, '0.00');

    // A row Gencys never stamped and one stamped with 0 are both "no upsell" —
    // the table draws no badge for either.
    $found = rmoUpsell('without');

    expect($found)->toHaveCount(2)
        ->and($found)->toContain($unstamped->id, $zero->id);
});

it('leaves the query alone for an empty or unknown value', function () {
    $a = makeUpsellDelivery($this->workspace, '499.00');
    $b = makeUpsellDelivery($this->workspace, null);

    expect(rmoUpsell(''))->toHaveCount(2)
        ->and(rmoUpsell('nonsense'))->toContain($a->id, $b->id);
});

it('accepts the boolean Spatie coerces a bare true/false into', function () {
    $withUpsell = makeUpsellDelivery($this->workspace, '499.00');
    $none = makeUpsellDelivery($this->workspace, null);

    expect(rmoUpsell(true))->toBe([$withUpsell->id])
        ->and(rmoUpsell(false))->toBe([$none->id]);
});
