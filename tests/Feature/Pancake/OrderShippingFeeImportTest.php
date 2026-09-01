<?php

use App\Enums\Permission;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Pancake\Jobs\ImportOrderShippingFees;
use Modules\Pancake\Support\ShippingFeeImportStatus as Status;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/** The courier's billing export, headings as they come out of the portal. */
function billingSheet(array $rows, array $headings = ['Creator Code', 'Waybill Number', 'Order Status', 'Total Shipping Cost', 'Express Type']): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'fees').'.xlsx';
    (new XlsxWriter($spreadsheet))->save($path);

    return $path;
}

function billingRow(string $waybill, float|string|null $cost): array
{
    return ['CRE-1', $waybill, 'Delivered', $cost, 'EZ'];
}

/** The sheet as the job finds it: already stored on the local disk. */
function storedSheet(array $rows, ?array $headings = null): string
{
    $path = $headings ? billingSheet($rows, $headings) : billingSheet($rows);
    $stored = 'imports/shipping-fees/'.basename($path);
    Storage::disk('local')->put($stored, file_get_contents($path));

    return $stored;
}

function uploadSheet(Workspace $workspace, string $path)
{
    return test()->post(
        "/workspaces/{$workspace->slug}/pancake/orders/shipping-fees/import",
        ['file' => new UploadedFile($path, 'august.xlsx', null, null, true)],
    );
}

function runImport(Workspace $workspace, string $storedPath): void
{
    (new ImportOrderShippingFees($workspace->id, $storedPath, 'august.xlsx'))->handle();
}

it('shows the last import on the orders page', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    Status::put($workspace->id, ['status' => Status::FINISHED, 'file' => 'august.xlsx', 'updated' => 12]);

    test()->get("/workspaces/{$workspace->slug}/pancake/orders")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/pancake/orders/index')
            ->where('shippingFeeImport.status', Status::FINISHED)
            ->where('shippingFeeImport.updated', 12));
});

it('queues the uploaded sheet instead of reading it in the request', function () {
    Queue::fake();
    Storage::fake('local');

    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    uploadSheet($workspace, billingSheet([billingRow('WB-1001', 105.5)]))
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(
        ImportOrderShippingFees::class,
        fn ($job) => $job->workspaceId === $workspace->id && $job->fileName === 'august.xlsx',
    );

    expect(Status::get($workspace->id))
        ->toMatchArray(['status' => Status::QUEUED, 'file' => 'august.xlsx']);
});

it('refuses a second sheet while one is still running', function () {
    Queue::fake();
    Storage::fake('local');

    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    Status::begin($workspace->id, 'earlier.xlsx');

    uploadSheet($workspace, billingSheet([billingRow('WB-1001', 105.5)]))
        ->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});

it('is closed to members without the import permission', function () {
    Queue::fake();

    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    test()->actingAs(makeMemberWithPermissions($workspace, [Permission::ViewOrders->value], 'Pancake'));

    uploadSheet($workspace, billingSheet([billingRow('WB-1001', 105.5)]))->assertForbidden();

    Queue::assertNothingPushed();
});

it('writes the shipping cost onto the orders the waybills name', function () {
    Storage::fake('local');
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $matched = Order::factory()->create([
        'workspace_id' => $workspace->id,
        'tracking_code' => 'WB-1001',
        'shipping_fee' => null,
    ]);
    $untouched = Order::factory()->create([
        'workspace_id' => $workspace->id,
        'tracking_code' => 'WB-OTHER',
        'shipping_fee' => null,
    ]);

    $stored = storedSheet([billingRow('WB-1001', 105.5), billingRow('WB-2002', 88)]);
    runImport($workspace, $stored);

    expect((float) $matched->fresh()->shipping_fee)->toBe(105.5)
        ->and($untouched->fresh()->shipping_fee)->toBeNull();

    expect(Status::get($workspace->id))->toMatchArray([
        'status' => Status::FINISHED,
        'rows_read' => 2,
        'matched_orders' => 1,
        'updated' => 1,
        'unmatched' => 1,
    ]);

    expect(Status::get($workspace->id)['unmatched_sample'])->toBe(['WB-2002']);

    // The upload is cleaned up once it has been read.
    Storage::disk('local')->assertMissing($stored);
});

it('re-reads a corrected sheet over the fee it already wrote', function () {
    Storage::fake('local');
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = Order::factory()->create([
        'workspace_id' => $workspace->id,
        'tracking_code' => 'WB-1001',
    ]);

    runImport($workspace, storedSheet([billingRow('WB-1001', 105.5)]));
    runImport($workspace, storedSheet([billingRow('WB-1001', 120)]));

    expect((float) $order->fresh()->shipping_fee)->toBe(120.0);
});

it('leaves an order alone when its row carries no cost', function () {
    Storage::fake('local');
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $order = Order::factory()->create([
        'workspace_id' => $workspace->id,
        'tracking_code' => 'WB-1001',
        'shipping_fee' => 42,
    ]);

    runImport($workspace, storedSheet([billingRow('WB-1001', '')]));

    expect((float) $order->fresh()->shipping_fee)->toBe(42.0)
        ->and(Status::get($workspace->id))->toMatchArray(['skipped' => 1, 'updated' => 0]);
});

it('only touches the workspace that uploaded the sheet', function () {
    Storage::fake('local');
    ['workspace' => $mine] = makeWorkspaceWithOwner();
    ['workspace' => $theirs] = makeWorkspaceWithOwner();

    $ours = Order::factory()->create(['workspace_id' => $mine->id, 'tracking_code' => 'WB-1001']);
    $others = Order::factory()->create([
        'workspace_id' => $theirs->id,
        'tracking_code' => 'WB-1001',
        'shipping_fee' => null,
    ]);

    runImport($mine, storedSheet([billingRow('WB-1001', 105.5)]));

    expect((float) $ours->fresh()->shipping_fee)->toBe(105.5)
        ->and($others->fresh()->shipping_fee)->toBeNull();
});

it('records a failure when the sheet has no shipping cost column', function () {
    Storage::fake('local');
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $stored = storedSheet([['CRE-1', 'WB-1001']], ['Creator Code', 'Waybill Number']);
    $job = new ImportOrderShippingFees($workspace->id, $stored, 'august.xlsx');

    try {
        $job->handle();
        test()->fail('the job should have thrown');
    } catch (RuntimeException $e) {
        $job->failed($e);
    }

    expect(Status::get($workspace->id))->toMatchArray(['status' => Status::FAILED])
        ->and(Status::get($workspace->id)['message'])->toContain('Total Shipping Cost');

    Storage::disk('local')->assertMissing($stored);
});
