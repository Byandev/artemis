<?php

use App\Models\Product;
use App\Models\User;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionProduct;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysDailySalesOrderItem;
use Modules\GencysERP\Models\Intern;
use Modules\Inventory\Models\InventoryUnitCode;

/** A delivered order credited to the given intern cell. */
function uis_order(int $id, $workspace, string $cell, array $attrs = []): GencysDailySalesOrder
{
    return GencysDailySalesOrder::create(array_merge([
        'id' => $id,
        'workspace_id' => $workspace->id,
        'intern_brands_name' => $cell,
        'parcel_status' => 'DELIVERED',
        'page' => 'FB Page',
        'platform' => 'Website',
        'parcel_updated_date' => '2026-05-12 09:00:00',
        'shipped_out_date' => '2026-05-06',
    ], $attrs));
}

function uis_statement($workspace): IncomeStatement
{
    // These fixtures seed gencys orders, and the flag selects the source.
    $workspace->update(['is_gencys_partner' => true]);

    return IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);
}

/**
 * An outflow of the given type, tagged to a product.
 *
 * The goods lines are read from these tags and then shared between the sellers
 * of the product by delivered orders — a user is reached by moving the product,
 * not by being charged for the purchase. Ad spend still uses uis_charge below.
 */
function uis_tagged($workspace, Account $account, string $typeName, string $product, float $amount): void
{
    $type = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => $typeName,
        'income_statement_section' => 'cost_of_sales',
    ]);

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-15',
        'description' => $typeName,
        'type' => 'out',
        'transaction_type_id' => $type->id,
        'amount' => $amount,
    ]);

    TransactionProduct::create([
        'transaction_id' => $txn->id,
        'product' => $product,
        'amount' => $amount,
    ]);
}

/** An outflow of the given type, charged to a user. */
function uis_charge($workspace, Account $account, string $typeName, User $user, float $amount): void
{
    $type = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => $typeName,
        'income_statement_section' => 'cost_of_sales',
    ]);

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-15',
        'description' => $typeName,
        'type' => 'out',
        'transaction_type_id' => $type->id,
        'amount' => $amount,
    ]);

    $txn->chargeToUsers()->sync([$user->id => ['amount' => $amount]]);
}

test('the statement snapshots a row per user through to gross profit', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    // The only product on the statement, so its whole purchase lands on the
    // only person delivering it.
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create([
        'workspace_id' => $workspace->id, 'unit_code' => 'UC-W', 'product_id' => $product->id,
    ]);

    // Two delivered parcels: 1,000 of revenue, 240 of goods, 100 of shipping.
    foreach ([920001, 920002] as $id) {
        $order = uis_order($id, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'total_cog' => 120, 'shipping_fee' => 50]);
        GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'UC-W', 'quantity' => 1]);
    }

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    uis_charge($workspace, $account, 'Ad Spent', $user, 300);
    uis_tagged($workspace, $account, 'Cost of Goods', 'WIDGET', 600);
    uis_tagged($workspace, $account, 'Delivery of COG', 'WIDGET', 40);

    $statement = uis_statement($workspace);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $row = $statement->userStatements()->where('user_id', $user->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->user_name)->toBe($user->name)
        ->and((int) $row->delivered_orders)->toBe(2)
        ->and((float) $row->delivered_amount)->toBe(1000.0)
        ->and((int) $row->shipped_orders)->toBe(2)
        ->and((float) $row->total_shipping_fee)->toBe(100.0)
        // Ad spend lands on the person it was charged to; the goods lines on
        // the person who delivered the product they were tagged to.
        ->and((float) $row->ad_spent)->toBe(300.0)
        ->and((float) $row->total_bought_cogs)->toBe(600.0)
        ->and((float) $row->total_bought_cogs_delivery_fee)->toBe(40.0)
        // 2% of 1,000, then 12% of that.
        ->and((float) $row->cod_fee)->toBe(20.0)
        ->and((float) $row->cod_fee_vat)->toBe(2.4)
        ->and((float) $row->total_delivered_cogs)->toBe(240.0)
        // Common costs are 300 + 100 + 20 + 2.40 = 422.40.
        // Delivered basis: 1000 − 422.40 − 240 = 337.60
        ->and((float) $row->gross_profit_delivered_cogs)->toBe(337.60)
        // Bought basis: 1000 − 422.40 − 600 − 40 = −62.40
        ->and((float) $row->gross_profit_bought_cogs)->toBe(-62.40);

    // Rebuilding replaces the rows rather than stacking them up.
    app(UserIncomeStatementService::class)->snapshot($statement);
    expect($statement->userStatements()->where('user_id', $user->id)->count())->toBe(1);
});

test('orders whose intern name matches nobody land in Unassigned', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    uis_order(930001, $workspace, 'Juan Dela Cruz', ['price_final' => 900, 'shipping_fee' => 40]);
    // A name nobody is linked to...
    uis_order(930002, $workspace, 'Ghost Intern', ['price_final' => 300, 'shipping_fee' => 30]);
    uis_order(930003, $workspace, 'Ghost Intern', ['price_final' => 200, 'shipping_fee' => 20]);
    // ...and an order with no name written on it at all.
    uis_order(930004, $workspace, '', ['price_final' => 70, 'shipping_fee' => 5]);

    $statement = uis_statement($workspace);
    $service = app(UserIncomeStatementService::class);
    $service->snapshot($statement);

    $unassigned = $statement->userStatements()->whereNull('user_id')->first();

    expect($unassigned)->not->toBeNull()
        ->and($unassigned->user_name)->toBe('Unassigned')
        ->and((int) $unassigned->delivered_orders)->toBe(3)
        ->and((float) $unassigned->delivered_amount)->toBe(570.0);

    // The breakdown says which names are behind it, biggest first.
    $rows = collect($service->unassignedBreakdown($statement));

    expect($rows->pluck('label'))->not->toContain('Juan Dela Cruz');

    $ghost = $rows->firstWhere('label', 'Ghost Intern');
    expect((int) $ghost['delivered_orders'])->toBe(2)
        ->and((float) $ghost['delivered_amount'])->toBe(500.0)
        ->and((float) $ghost['shipping_fee'])->toBe(50.0)
        ->and($rows->first()['label'])->toBe('Ghost Intern')
        // The nameless order comes back under a null cell.
        ->and($rows->firstWhere('label', null)['delivered_amount'])->toBe(70.0);

    // And the breakdown adds up to the row it explains.
    expect(round($rows->sum('delivered_amount'), 2))->toBe((float) $unassigned->delivered_amount)
        ->and(round($rows->sum('shipping_fee'), 2))->toBe((float) $unassigned->total_shipping_fee);
});

test('the user statement page reads the saved rows', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    uis_order(940001, $workspace, 'Juan Dela Cruz', ['price_final' => 1000]);
    uis_order(940002, $workspace, 'Ghost Intern', ['price_final' => 50]);

    $statement = uis_statement($workspace);

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/users")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/user-income-statements/index')
            ->where('users', function ($users) use ($user) {
                $rows = collect($users);

                return $rows->first()['user'] === $user->name
                    // The unassigned row sits last, outside the named users.
                    && $rows->last()['user_id'] === null;
            })
            // The Total covers the named users only — 1,000, not the 50.
            ->where('total.delivered_amount', fn ($v) => (float) $v === 1000.0)
            ->where('total.delivered_orders', 1)
            ->where('total.cod_fee', fn ($v) => (float) $v === 20.0)
            ->where('rates.cod', fn ($v) => (float) $v === 0.02)
            // The Unassigned row can be opened up to show what's behind it.
            ->where('unassigned', fn ($rows) => collect($rows)->pluck('label')->contains('Ghost Intern'))
        );

    // The page built the snapshot on first view.
    expect($statement->userStatements()->count())->toBe(2);
});
