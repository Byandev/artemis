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

    // WIDGET: 1000 − (ship 50 + COD 20 + VAT 2.40) = 927.60. The order's
    // `total_cog` is ignored — COGS comes in as a transaction instead.
    $widget = $products->firstWhere('product', 'WIDGET');
    expect($widget)->not->toBeNull()
        ->and($widget['product_id'])->toBe($product->id)
        ->and((float) $widget['delivered'])->toBe(1000.0)
        ->and((float) $widget['cost_of_sales'])->toBe(72.40)
        ->and((float) $widget['gross_profit'])->toBe(927.60)
        ->and((int) $widget['orders'])->toBe(1);

    // The order whose item resolves to no product lands in "Discrepancy", last.
    expect($products->last()['product'])->toBe('Discrepancy');

    $discrepancy = $products->firstWhere('product', 'Discrepancy');
    expect($discrepancy)->not->toBeNull()
        ->and($discrepancy['product_id'])->toBeNull()
        ->and((float) $discrepancy['delivered'])->toBe(500.0)
        // 500 − (ship 0 + COD 10 + VAT 1.20) = 488.80
        ->and((float) $discrepancy['gross_profit'])->toBe(488.80);

    // The unresolved unit code surfaces in the missing-unit-codes warning list;
    // the mapped one does not.
    $missing = collect($service->missingUnitCodes($statement))->pluck('unit_code');
    expect($missing)->toContain('NOPE')
        ->and($missing)->not->toContain('UC1');
});

/** The per-user statement page, seeded with one mapped product + one unmapped order. */
function ups_seed(): array
{
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);

    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => 'UC1',
        'product_id' => $product->id,
    ]);

    ups_order(810001, $workspace, 'Juan Dela Cruz', [
        'price_final' => 1000, 'shipping_fee' => 50,
    ], 'UC1');
    ups_order(810002, $workspace, 'Juan Dela Cruz', [
        'price_final' => 500, 'shipping_fee' => 0,
    ], 'NOPE');

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    return [$user, $workspace, $statement, $product];
}

test('the per-user statement page carries the product breakdown', function () {
    [$user, $workspace, $statement, $product] = ups_seed();

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/users/{$user->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/income-statements/show')
            ->where('scope.label', $user->name)
            // The commission endpoint the breakdown's rate input posts to.
            ->where('commissionUrl', "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/users/{$user->id}/commission-rate")
            ->where('statement.products', fn ($products) => collect($products)->firstWhere('product', 'WIDGET') !== null
                && (float) collect($products)->firstWhere('product', 'WIDGET')['delivered'] === 1000.0
                && (float) collect($products)->firstWhere('product', 'WIDGET')['commission_rate'] === 0.0
                // The unresolved order is kept, last, as "Discrepancy".
                && collect($products)->last()['product'] === 'Discrepancy')
        );
});

test('saving a commission rate feeds the product breakdown', function () {
    [$user, $workspace, $statement, $product] = ups_seed();

    $base = "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/users/{$user->id}";

    $this->actingAs($user)
        ->put("{$base}/commission-rate", ['product_id' => $product->id, 'rate' => 0.05])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_commission_rates', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'product_id' => $product->id,
        'rate' => 0.05,
    ]);

    // WIDGET nets 927.60 gross; a gencys partner isn't set, so net = gross.
    // Commission = 5% of net profit.
    $this->actingAs($user)
        ->get($base)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('statement.products', function ($products) {
                $widget = collect($products)->firstWhere('product', 'WIDGET');

                return (float) $widget['commission_rate'] === 0.05
                    && (float) $widget['commission'] === round((float) $widget['net_profit'] * 0.05, 2);
            })
        );
});
