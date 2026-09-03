<?php

use App\Jobs\SyncCsrRmoDailyRecord;
use App\Models\Order;
use App\Models\PancakeUserRmoDailyReport;
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

function rmoDeliveryFor($workspace, PancakeUser $csr, string $date, string $status = 'CALLED'): void
{
    $order = Order::factory()->forWorkspace($workspace)->create();

    OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => $status,
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => '09170000001',
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
