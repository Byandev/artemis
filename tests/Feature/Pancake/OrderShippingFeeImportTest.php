<?php

use App\Enums\Permission;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/** The courier's billing export, headings as they come out of the portal. */
function billingSheet(array $rows, array $headings = ['Creator Code', 'Waybill Number', 'Order Status', 'Total Shipping Cost', 'Express Type']): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'fees').'.xlsx';
    (new XlsxWriter($spreadsheet))->save($path);

    return new UploadedFile($path, 'august.xlsx', null, null, true);
}

function billingRow(string $waybill, float|string|null $cost): array
{
    return ['CRE-1', $waybill, 'Delivered', $cost, 'EZ'];
}

function importSheet(Workspace $workspace, UploadedFile $file)
{
    return test()->post(
        "/workspaces/{$workspace->slug}/pancake/orders/shipping-fees/import",
        ['file' => $file],
    );
}

it('renders the orders page', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    test()->get("/workspaces/{$workspace->slug}/pancake/orders")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('workspaces/pancake/orders/index'));
});

it('writes the shipping cost onto the orders the waybills name', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

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

    importSheet($workspace, billingSheet([
        billingRow('WB-1001', 105.5),
        billingRow('WB-2002', 88),
    ]))
        ->assertRedirect()
        ->assertSessionHas('success', fn ($m) => str_contains($m, '1 of 1 matched orders updated')
            && str_contains($m, '1 waybills matched no order')
            && str_contains($m, 'WB-2002'));

    expect((float) $matched->fresh()->shipping_fee)->toBe(105.5)
        ->and($untouched->fresh()->shipping_fee)->toBeNull();
});

it('re-reads a corrected sheet over the fee it already wrote', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $order = Order::factory()->create([
        'workspace_id' => $workspace->id,
        'tracking_code' => 'WB-1001',
    ]);

    importSheet($workspace, billingSheet([billingRow('WB-1001', 105.5)]));
    importSheet($workspace, billingSheet([billingRow('WB-1001', 120)]));

    expect((float) $order->fresh()->shipping_fee)->toBe(120.0);
});

it('leaves an order alone when its row carries no cost', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $order = Order::factory()->create([
        'workspace_id' => $workspace->id,
        'tracking_code' => 'WB-1001',
        'shipping_fee' => 42,
    ]);

    importSheet($workspace, billingSheet([billingRow('WB-1001', '')]))
        ->assertSessionHas('success', fn ($m) => str_contains($m, '1 rows had no shipping cost'));

    expect((float) $order->fresh()->shipping_fee)->toBe(42.0);
});

it('only touches the workspace that uploaded the sheet', function () {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    ['workspace' => $theirs] = makeWorkspaceWithOwner();

    $ours = Order::factory()->create(['workspace_id' => $mine->id, 'tracking_code' => 'WB-1001']);
    $others = Order::factory()->create([
        'workspace_id' => $theirs->id,
        'tracking_code' => 'WB-1001',
        'shipping_fee' => null,
    ]);

    importSheet($mine, billingSheet([billingRow('WB-1001', 105.5)]));

    expect((float) $ours->fresh()->shipping_fee)->toBe(105.5)
        ->and($others->fresh()->shipping_fee)->toBeNull();
});

it('reports a sheet with no shipping cost column', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    importSheet($workspace, billingSheet(
        [['CRE-1', 'WB-1001']],
        ['Creator Code', 'Waybill Number'],
    ))->assertSessionHas('error', fn ($m) => str_contains($m, 'Total Shipping Cost'));
});

it('is closed to members without the import permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeMemberWithPermissions($workspace, [Permission::ViewOrders->value], 'Pancake');

    test()->actingAs($member);

    importSheet($workspace, billingSheet([billingRow('WB-1001', 105.5)]))->assertForbidden();
});
