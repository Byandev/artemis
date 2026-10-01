<?php

use App\Http\Controllers\Workspaces\RTS\ForDeliveryController;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * Run the private external-team filter against a real query, the same way the
 * `filter[rmo_by_external_team]` AllowedFilter callback does.
 */
function rmoExternalTeam(mixed $value): array
{
    $query = OrderForDelivery::query();

    $method = new ReflectionMethod(ForDeliveryController::class, 'applyExternalTeamFilter');
    $method->invoke(
        (new ReflectionClass(ForDeliveryController::class))->newInstanceWithoutConstructor(),
        $query,
        $value,
    );

    return $query->pluck('id')->all();
}

function makeExternalTeamDelivery(Workspace $workspace, ?bool $external): OrderForDelivery
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
        // null leaves the column to its default, as rows synced before the
        // column existed do.
        ...($external === null ? [] : ['rmo_by_external_team' => $external]),
    ])->refresh();
}

beforeEach(function () {
    $this->workspace = makeWorkspaceWithOwner()['workspace'];
});

it('defaults new rows to the internal team', function () {
    expect(makeExternalTeamDelivery($this->workspace, null)->rmo_by_external_team)->toBeFalse();
});

it('keeps only orders RMO\'d by an external team', function () {
    $external = makeExternalTeamDelivery($this->workspace, true);
    makeExternalTeamDelivery($this->workspace, false);
    makeExternalTeamDelivery($this->workspace, null);

    expect(rmoExternalTeam('external'))->toBe([$external->id]);
});

it('keeps only orders RMO\'d by the internal team', function () {
    makeExternalTeamDelivery($this->workspace, true);
    $internal = makeExternalTeamDelivery($this->workspace, false);
    $defaulted = makeExternalTeamDelivery($this->workspace, null);

    $found = rmoExternalTeam('internal');

    expect($found)->toHaveCount(2)
        ->and($found)->toContain($internal->id, $defaulted->id);
});

it('leaves the query alone for an empty or unknown value', function () {
    makeExternalTeamDelivery($this->workspace, true);
    makeExternalTeamDelivery($this->workspace, false);

    expect(rmoExternalTeam(''))->toHaveCount(2)
        ->and(rmoExternalTeam('nonsense'))->toHaveCount(2);
});

it('accepts the boolean Spatie coerces a bare true/false into', function () {
    $external = makeExternalTeamDelivery($this->workspace, true);
    $internal = makeExternalTeamDelivery($this->workspace, false);

    expect(rmoExternalTeam(true))->toBe([$external->id])
        ->and(rmoExternalTeam(false))->toBe([$internal->id]);
});
