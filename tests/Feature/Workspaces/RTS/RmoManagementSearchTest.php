<?php

use App\Http\Controllers\Workspaces\RTS\ForDeliveryController;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * Run the private search filter against a real query, the same way the
 * `filter[search]` AllowedFilter callback does.
 */
function rmoSearch(mixed $value): array
{
    $query = OrderForDelivery::query();

    $method = new ReflectionMethod(ForDeliveryController::class, 'applyRmoSearch');
    $method->invoke(
        (new ReflectionClass(ForDeliveryController::class))->newInstanceWithoutConstructor(),
        $query,
        $value,
    );

    return $query->pluck('id')->all();
}

function makeDelivery(string $trackingCode, string $orderNumber): OrderForDelivery
{
    $order = Order::create([
        'order_number' => $orderNumber,
        'tracking_code' => $trackingCode,
    ]);

    return OrderForDelivery::create([
        'order_id' => $order->id,
        'workspace_id' => 1,
        'delivery_date' => now()->toDateString(),
    ]);
}

it('matches a single tracking code', function () {
    $a = makeDelivery('TRK-AAA', 'ORD-1');
    makeDelivery('TRK-BBB', 'ORD-2');

    expect(rmoSearch('TRK-AAA'))->toBe([$a->id]);
});

it('matches any of several comma-separated tracking codes', function () {
    $a = makeDelivery('TRK-AAA', 'ORD-1');
    $b = makeDelivery('TRK-BBB', 'ORD-2');
    $c = makeDelivery('TRK-CCC', 'ORD-3');
    makeDelivery('TRK-DDD', 'ORD-4');

    // Spatie explodes "TRK-AAA,TRK-BBB,TRK-CCC" into an array before the
    // filter callback sees it.
    $found = rmoSearch(['TRK-AAA', 'TRK-BBB', 'TRK-CCC']);

    expect($found)->toHaveCount(3)
        ->and($found)->toContain($a->id, $b->id, $c->id);
});

it('accepts a raw comma-delimited string', function () {
    $a = makeDelivery('TRK-AAA', 'ORD-1');
    $b = makeDelivery('TRK-BBB', 'ORD-2');
    makeDelivery('TRK-CCC', 'ORD-3');

    $found = rmoSearch('TRK-AAA,TRK-BBB');

    expect($found)->toHaveCount(2)
        ->and($found)->toContain($a->id, $b->id);
});

it('trims whitespace and ignores blank terms', function () {
    $a = makeDelivery('TRK-AAA', 'ORD-1');
    $b = makeDelivery('TRK-BBB', 'ORD-2');
    makeDelivery('TRK-CCC', 'ORD-3');

    $found = rmoSearch(' TRK-AAA , , TRK-BBB ,');

    expect($found)->toHaveCount(2)
        ->and($found)->toContain($a->id, $b->id);
});

it('mixes term types — an order number alongside a tracking code', function () {
    $a = makeDelivery('TRK-AAA', 'ORD-1');
    $b = makeDelivery('TRK-BBB', 'ORD-2');
    makeDelivery('TRK-CCC', 'ORD-3');

    $found = rmoSearch('ORD-1,TRK-BBB');

    expect($found)->toHaveCount(2)
        ->and($found)->toContain($a->id, $b->id);
});

it('applies no constraint for an empty search', function () {
    makeDelivery('TRK-AAA', 'ORD-1');
    makeDelivery('TRK-BBB', 'ORD-2');

    expect(rmoSearch(''))->toHaveCount(2);
});
