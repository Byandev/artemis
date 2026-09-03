<?php

use App\Jobs\SyncCsrDailyRecord;
use App\Models\Order;
use App\Models\PancakeUserPosDailyReport;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The nightly POS rollup, and specifically the parcel counts beside the money —
 * which the job computed but did not persist until CSR analytics needed them.
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->csr = PancakeUser::create(['name' => 'Angeline Mercado']);
});

function syncedRow($workspace, $csr, string $date): ?PancakeUserPosDailyReport
{
    (new SyncCsrDailyRecord($date))->handle();

    return PancakeUserPosDailyReport::query()
        ->where('workspace_id', $workspace->id)
        ->where('pancake_user_id', $csr->id)
        ->whereDate('date', $date)
        ->first();
}

test('the rollup keeps the parcel counts beside the amounts', function () {
    Order::factory()->forWorkspace($this->workspace)->create([
        'status' => 3,
        'final_amount' => 800,
        'delivered_at' => '2026-08-02 10:00:00',
        'confirmed_by' => $this->csr->id,
    ]);

    Order::factory()->forWorkspace($this->workspace)->create([
        'status' => 4,
        'final_amount' => 200,
        'returning_at' => '2026-08-02 11:00:00',
        'confirmed_by' => $this->csr->id,
    ]);

    $row = syncedRow($this->workspace, $this->csr, '2026-08-02');

    expect((float) $row->delivered)->toBe(800.0)
        ->and((int) $row->delivered_count)->toBe(1)
        ->and((float) $row->returning)->toBe(200.0)
        ->and((int) $row->returning_count)->toBe(1);
});

test('a parcel worth nothing still counts as a parcel', function () {
    Order::factory()->forWorkspace($this->workspace)->create([
        'status' => 3,
        'final_amount' => 0,
        'delivered_at' => '2026-08-02 10:00:00',
        'confirmed_by' => $this->csr->id,
    ]);

    $row = syncedRow($this->workspace, $this->csr, '2026-08-02');

    // The amount says nothing happened; the count keeps the CSR eligible.
    expect((float) $row->delivered)->toBe(0.0)
        ->and((int) $row->delivered_count)->toBe(1);
});

test('the counts follow the day the parcel settled, not the day it was taken', function () {
    Order::factory()->forWorkspace($this->workspace)->create([
        'status' => 3,
        'final_amount' => 500,
        'confirmed_at' => '2026-08-01 09:00:00',
        'delivered_at' => '2026-08-02 10:00:00',
        'confirmed_by' => $this->csr->id,
    ]);

    $taken = syncedRow($this->workspace, $this->csr, '2026-08-01');
    $settled = syncedRow($this->workspace, $this->csr, '2026-08-02');

    // The 1st carries the order and the sale, no parcel.
    expect((int) $taken->total_orders)->toBe(1)
        ->and((int) $taken->delivered_count)->toBe(0);

    // The 2nd carries the parcel, and nothing taken.
    expect((int) $settled->delivered_count)->toBe(1)
        ->and((int) $settled->total_orders)->toBe(0);
});
