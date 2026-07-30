<?php

use App\Models\Product;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysDailySalesOrderItem;
use Modules\GencysERP\Models\Intern;
use Modules\Inventory\Models\InventoryUnitCode;

/** A delivered order for the given intern cell, with a unit-code item. */
function ups_order(int $id, $workspace, string $cell, array $attrs, ?string $unitCode): GencysDailySalesOrder
{
    $order = GencysDailySalesOrder::create(array_merge([
        'id' => $id,
        'workspace_id' => $workspace->id,
        'intern_brands_name' => $cell,
        'parcel_status' => 'DELIVERED',
        'page' => 'FB Page',        // non-null so the `page NOT LIKE %pikutin%` filter keeps it
        'platform' => 'Website',
        'parcel_updated_date' => '2026-05-12 09:00:00',
        'shipped_out_date' => '2026-05-06',
    ], $attrs));

    if ($unitCode !== null) {
        GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => $unitCode, 'quantity' => 1]);
    }

    return $order;
}

test('the per-product statement resolves the product through unit-code order items', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $intern = Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    $product = Product::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'WIDGET',
    ]);

    // Unit code UC1 maps to WIDGET; the order item's sku carries that unit code.
    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => 'UC1',
        'product_id' => $product->id,
    ]);

    // A mapped order (→ WIDGET) and an unmapped one (item sku matches no unit code).
    ups_order(800001, $workspace, 'Juan Dela Cruz', [
        'price_final' => 1000, 'total_cog' => 200, 'shipping_fee' => 50,
    ], 'UC1');
    ups_order(800002, $workspace, 'Juan Dela Cruz', [
        'price_final' => 500, 'total_cog' => 100, 'shipping_fee' => 0,
    ], 'NOPE');

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $service = app(UserIncomeStatementService::class);
    $products = collect($service->userPayload($statement, $user)['products']);

    // WIDGET: 1000 − (COGS 200 + ship 50 + COD 20 + VAT 2.40) = 727.60
    $widget = $products->firstWhere('product', 'WIDGET');
    expect($widget)->not->toBeNull()
        ->and($widget['product_id'])->toBe($product->id)
        ->and((float) $widget['delivered'])->toBe(1000.0)
        ->and((float) $widget['cost_of_sales'])->toBe(272.40)
        ->and((float) $widget['gross_profit'])->toBe(727.60)
        ->and((int) $widget['orders'])->toBe(1);

    // The order whose item resolves to no product lands in "Discrepancy", last.
    expect($products->last()['product'])->toBe('Discrepancy');

    $discrepancy = $products->firstWhere('product', 'Discrepancy');
    expect($discrepancy)->not->toBeNull()
        ->and($discrepancy['product_id'])->toBeNull()
        ->and((float) $discrepancy['delivered'])->toBe(500.0)
        // 500 − (100 + 0 + 10 + 1.20) = 388.80
        ->and((float) $discrepancy['gross_profit'])->toBe(388.80);

    // The unresolved unit code surfaces in the missing-unit-codes warning list;
    // the mapped one does not.
    $missing = collect($service->missingUnitCodes($statement))->pluck('unit_code');
    expect($missing)->toContain('NOPE')
        ->and($missing)->not->toContain('UC1');
});
