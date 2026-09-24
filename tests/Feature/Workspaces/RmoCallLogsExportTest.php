<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * Workspace with one delivery row assigned to a CSR, ready for call logs to be
 * hung off its customer and rider numbers.
 */
function rmoCallLogContext(array $overrides = []): array
{
    $ctx = makeWorkspaceWithOwner();
    $workspace = $ctx['workspace'];

    $csr = PancakeUser::create(['name' => 'CSR One']);

    $order = Order::factory()->forWorkspace($workspace)->create([
        'order_number' => 'ORD-0001',
        'tracking_code' => 'TRK-0001',
    ]);

    $delivery = OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'rider_name' => 'Rider One',
        'rider_phone' => '09170000002',
        'customer_name' => 'Cx One',
        'customer_phone' => '09170000001',
        'assignee_id' => $csr->id,
        'delivery_date' => '2026-07-20',
        ...$overrides,
    ]);

    return [$ctx['user'], $workspace, $csr, $delivery];
}

function makeCallLog(int $workspaceId, string $csrId, string $phone, array $overrides = []): CallLog
{
    return CallLog::factory()->create([
        'workspace_id' => $workspaceId,
        'user_id' => $csrId,
        'phone_number' => $phone,
        'call_date' => '2026-07-20',
        ...$overrides,
    ]);
}

/** The rows an export produced, without the heading row. */
function exportedRows($export): array
{
    return $export->collection()->all();
}

test('it exports every customer and rider call for the delivery date', function () {
    [$owner, $workspace, $csr, $delivery] = rmoCallLogContext();

    makeCallLog($workspace->id, $csr->id, $delivery->customer_phone, [
        'type' => 'outgoing', 'duration' => 42, 'call_time' => '09:15:00',
    ]);
    makeCallLog($workspace->id, $csr->id, $delivery->rider_phone, [
        'type' => 'incoming', 'duration' => 17, 'call_time' => '10:30:00',
    ]);

    Excel::fake();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/csr/rmo-management/call-logs/export?delivery_date=2026-07-20")
        ->assertOk();

    Excel::assertDownloaded('rmo-call-logs-2026-07-20-'.now()->format('His').'.xlsx', function ($export) {
        $rows = exportedRows($export);

        expect($rows)->toHaveCount(2);

        // [date, time, contact, phone, type, duration, csr, order number, ...]
        expect($rows[0][2])->toBe('Customer')
            ->and($rows[0][4])->toBe('outgoing')
            ->and($rows[0][5])->toBe(42)
            ->and($rows[0][6])->toBe('CSR One')
            ->and($rows[0][7])->toBe('ORD-0001');

        expect($rows[1][2])->toBe('Rider')
            ->and($rows[1][3])->toBe('09170000002');

        return true;
    });
});

test('calls on another date or by another CSR are left out', function () {
    [$owner, $workspace, $csr, $delivery] = rmoCallLogContext();

    makeCallLog($workspace->id, $csr->id, $delivery->customer_phone);

    // Same number, different day — belongs to another delivery date's report.
    makeCallLog($workspace->id, $csr->id, $delivery->customer_phone, [
        'call_date' => '2026-07-19',
    ]);

    // Same number and day, but dialled by a CSR the order isn't assigned to.
    $otherCsr = PancakeUser::create(['name' => 'CSR Two']);
    makeCallLog($workspace->id, $otherCsr->id, $delivery->customer_phone);

    Excel::fake();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/csr/rmo-management/call-logs/export?delivery_date=2026-07-20")
        ->assertOk();

    Excel::assertDownloaded('rmo-call-logs-2026-07-20-'.now()->format('His').'.xlsx', function ($export) {
        expect(exportedRows($export))->toHaveCount(1);

        return true;
    });
});

test('the page filters narrow the exported calls', function () {
    [$owner, $workspace, $csr, $delivery] = rmoCallLogContext();

    makeCallLog($workspace->id, $csr->id, $delivery->customer_phone);

    Excel::fake();

    // The row is PENDING, so filtering to CALLED must empty the export.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/csr/rmo-management/call-logs/export?delivery_date=2026-07-20&filter[status]=CALLED")
        ->assertOk();

    Excel::assertDownloaded('rmo-call-logs-2026-07-20-'.now()->format('His').'.xlsx', function ($export) {
        expect(exportedRows($export))->toBeEmpty();

        return true;
    });
});

test('the orders export still resolves the same filtered rows', function () {
    // Both exports share one filtered-query builder, so this guards the orders
    // export against changes made for the call-log one.
    [$owner, $workspace] = rmoCallLogContext();

    Excel::fake();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/csr/rmo-management/export?delivery_date=2026-07-20")
        ->assertOk();

    Excel::assertDownloaded('rmo-management-2026-07-20-'.now()->format('His').'.xlsx', function ($export) {
        expect($export->query()->count())->toBe(1);

        return true;
    });

    Excel::fake();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/csr/rmo-management/export?delivery_date=2026-07-21")
        ->assertOk();

    Excel::assertDownloaded('rmo-management-2026-07-21-'.now()->format('His').'.xlsx', function ($export) {
        expect($export->query()->count())->toBe(0);

        return true;
    });
});

test('the public route needs the workspace password unlocked', function () {
    [, $workspace] = rmoCallLogContext();
    subscribeWorkspace($workspace);

    $this->get("/public/workspaces/{$workspace->slug}/rts/rmo-management/call-logs/export?delivery_date=2026-07-20")
        ->assertForbidden();
});

test('a signed-in non-member cannot export another workspace call logs', function () {
    [, $workspace] = rmoCallLogContext();

    $this->actingAs(User::factory()->create())
        ->get("/workspaces/{$workspace->slug}/csr/rmo-management/call-logs/export?delivery_date=2026-07-20")
        ->assertForbidden();
});
