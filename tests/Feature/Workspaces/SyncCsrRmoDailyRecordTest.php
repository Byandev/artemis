<?php

use App\Jobs\SyncCsrRmoDailyRecord;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\PancakeUserRmoDailyReport;
use App\Models\Shop;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The nightly RMO rollup, and the --workspace scope on it.
 *
 * Rebuilding every workspace is right for the scheduled pass and wrong when you
 * are re-running to fix one client's figures, so the job takes a workspace id
 * and narrows every scan it makes to it.
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->csr = PancakeUser::create(['name' => 'Angeline Mercado']);
});

function rmoDeliveryFor($workspace, PancakeUser $csr, string $date, string $status = 'CALLED', ?Shop $shop = null, string $customerPhone = '09170000001'): void
{
    $order = Order::factory()->forWorkspace($workspace)->create(
        $shop ? ['shop_id' => $shop->id] : []
    );

    OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => $status,
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => $customerPhone,
        'rider_name' => 'Rider',
        'rider_phone' => '09180000001',
        'assignee_id' => $csr->id,
        'delivery_date' => $date,
    ]);
}

test('the rollup counts the deliveries a CSR was assigned that moved off PENDING', function () {
    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED');
    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'PENDING');

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $row = PancakeUserRmoDailyReport::where('workspace_id', $this->workspace->id)->first();

    expect((int) $row->total_called)->toBe(1);
});

test('a scoped rebuild leaves the other workspaces alone', function () {
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $theirCsr = PancakeUser::create(['name' => 'Elsewhere CSR']);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02');
    rmoDeliveryFor($other, $theirCsr, '2026-08-02');

    (new SyncCsrRmoDailyRecord('2026-08-02', $this->workspace->id))->handle();

    expect(PancakeUserRmoDailyReport::where('workspace_id', $this->workspace->id)->count())->toBe(1)
        ->and(PancakeUserRmoDailyReport::where('workspace_id', $other->id)->count())->toBe(0);
});

test('an unscoped rebuild still covers every workspace', function () {
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $theirCsr = PancakeUser::create(['name' => 'Elsewhere CSR']);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02');
    rmoDeliveryFor($other, $theirCsr, '2026-08-02');

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    expect(PancakeUserRmoDailyReport::count())->toBe(2);
});

test('the command takes the workspace by slug or by id', function () {
    foreach ([$this->workspace->slug, (string) $this->workspace->id] as $option) {
        $this->artisan('sync:csr-rmo-daily-records', [
            '--date' => '2026-08-02',
            '--workspace' => $option,
        ])->assertSuccessful();
    }
});

test('the command refuses a workspace it cannot find rather than rebuilding them all', function () {
    $this->artisan('sync:csr-rmo-daily-records', [
        '--date' => '2026-08-02',
        '--workspace' => 'no-such-workspace',
    ])->assertFailed();
});

/**
 * The rollup's grain: one row per CSR per shop per day, like the POS side.
 * Everything that reads it groups by CSR, so its totals are unchanged.
 */
test('a CSR working two shops gets a row for each', function () {
    $first = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
    $second = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $first);
    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $second);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $rows = PancakeUserRmoDailyReport::where('pancake_user_id', $this->csr->id)->get();

    expect($rows)->toHaveCount(2)
        ->and((int) $rows->firstWhere('shop_id', $first->id)->total_called)->toBe(1)
        ->and((int) $rows->firstWhere('shop_id', $second->id)->total_called)->toBe(1)
        ->and((int) $rows->sum('total_called'))->toBe(2);
});

test('the row carries the shop the delivery was in', function () {
    $shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $shop);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $row = PancakeUserRmoDailyReport::where('pancake_user_id', $this->csr->id)->first();

    expect((int) $row->shop_id)->toBe((int) $shop->id);
});

/**
 * The invariant the split had to keep: a number can belong to deliveries in two
 * shops, and the call about it is still one call. Counting it under both would
 * hand the CSR twice the talk time they actually spent.
 */
/**
 * Calls are counted through the order they carry, stamped at ingest — so a call
 * lands in exactly one shop, and one that was about no delivery is not RMO work.
 */
function rmoCallLog(array $attributes = []): CallLog
{
    return CallLog::create([
        'workspace_id' => test()->workspace->id,
        'user_id' => test()->csr->id,
        'phone_number' => '09170009999',
        'type' => 'outgoing',
        'duration' => 120,
        'call_date' => '2026-08-02',
        'call_time' => '10:00:00',
        ...$attributes,
    ]);
}

test('a call is counted under the shop of the order it names', function () {
    $shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
    $other = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $shop);
    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $other);

    $order = OrderForDelivery::where('shop_id', $shop->id)->value('order_id');
    rmoCallLog(['order_id' => $order]);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $rows = PancakeUserRmoDailyReport::where('pancake_user_id', $this->csr->id)->get();

    expect((int) $rows->firstWhere('shop_id', $shop->id)->total_rmo_call_attempts)->toBe(1)
        ->and((int) $rows->firstWhere('shop_id', $shop->id)->total_call_time)->toBe(120)
        // The other shop had deliveries but no calls about them.
        ->and((int) $rows->firstWhere('shop_id', $other->id)->total_rmo_call_attempts)->toBe(0);
});

test('a call about no order is not RMO work', function () {
    $shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $shop);

    // Left unstamped at ingest because it matched no delivery — an
    // order-verification call, not a call about a parcel.
    rmoCallLog(['order_id' => null]);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $rows = PancakeUserRmoDailyReport::where('pancake_user_id', $this->csr->id)->get();

    expect((int) $rows->sum('total_rmo_call_attempts'))->toBe(0)
        ->and((int) $rows->sum('total_call_time'))->toBe(0);
});

test('two calls about the same order add up on its shop', function () {
    $shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $shop);
    $order = OrderForDelivery::where('shop_id', $shop->id)->value('order_id');

    rmoCallLog(['order_id' => $order, 'duration' => 120, 'call_time' => '10:00:00']);
    rmoCallLog(['order_id' => $order, 'duration' => 45, 'call_time' => '11:00:00']);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $row = PancakeUserRmoDailyReport::where('pancake_user_id', $this->csr->id)->first();

    expect((int) $row->total_rmo_call_attempts)->toBe(2)
        ->and((int) $row->total_call_time)->toBe(165);
});

test('re-running the sync updates the rows rather than adding more', function () {
    $shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $shop);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();
    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    expect(PancakeUserRmoDailyReport::count())->toBe(1);
});

/**
 * The two roles come off one scan now, so what used to fall out of a key union
 * is the aggregate's job: a CSR who only confirmed still gets a row, and one
 * who did both in a shop gets a single row carrying both figures.
 */
function rmoConfirmedBy($workspace, PancakeUser $csr, string $date, ?Shop $shop = null, ?PancakeUser $assignee = null): void
{
    $order = Order::factory()->forWorkspace($workspace)->create(
        $shop ? ['shop_id' => $shop->id] : []
    );

    OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'CALLED',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09170003333',
        'rider_name' => 'Rider',
        'rider_phone' => '09180003333',
        'assignee_id' => $assignee?->id,
        'conferrer_id' => $csr->id,
        'delivery_date' => $date,
    ]);
}

test('a CSR who only confirmed still gets a row', function () {
    $shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoConfirmedBy($this->workspace, $this->csr, '2026-08-02', $shop);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $row = PancakeUserRmoDailyReport::where('pancake_user_id', $this->csr->id)->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->total_confirmed)->toBe(1)
        // Nothing was assigned to them, so nothing was theirs to call.
        ->and((int) $row->total_called)->toBe(0);
});

test('a CSR who was assigned and confirmed in one shop gets one row', function () {
    $shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $shop);
    rmoConfirmedBy($this->workspace, $this->csr, '2026-08-02', $shop);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $rows = PancakeUserRmoDailyReport::where('pancake_user_id', $this->csr->id)->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->total_called)->toBe(1)
        ->and((int) $rows->first()->total_confirmed)->toBe(1);
});

test('confirming in one shop and being assigned in another gives a row each', function () {
    $confirmed = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
    $assigned = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoConfirmedBy($this->workspace, $this->csr, '2026-08-02', $confirmed);
    rmoDeliveryFor($this->workspace, $this->csr, '2026-08-02', 'CALLED', $assigned);

    (new SyncCsrRmoDailyRecord('2026-08-02'))->handle();

    $rows = PancakeUserRmoDailyReport::where('pancake_user_id', $this->csr->id)->get();

    expect($rows)->toHaveCount(2)
        ->and((int) $rows->firstWhere('shop_id', $confirmed->id)->total_confirmed)->toBe(1)
        ->and((int) $rows->firstWhere('shop_id', $confirmed->id)->total_called)->toBe(0)
        ->and((int) $rows->firstWhere('shop_id', $assigned->id)->total_called)->toBe(1)
        ->and((int) $rows->firstWhere('shop_id', $assigned->id)->total_confirmed)->toBe(0);
});
