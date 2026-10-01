<?php

use App\Models\Page;
use App\Models\PageDailyRecord;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionProduct;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\Finance\Services\UserProductIncomeStatementService;
use Modules\Finance\Statements\StatementOrderSourceFactory;
use Modules\Pancake\Models\Order as PancakeOrder;
use Modules\Products\Models\Product;

/**
 * The three slices are the same month cut three ways, so they have to come to
 * the same totals — down to the net profit at the bottom.
 *
 * They only do because the closing lines are allocated from the parent
 * statement rather than recomputed per row (see ClosesOutSlices). Struck per
 * row they would not: the advisory charges the lower of two bases and forgives
 * a loss, and the COD fee rounds per row, so grouping the same orders eight
 * ways and thirty-four ways would land on different totals.
 *
 * Written on the pancake side — the non-gencys source, where a page has an
 * owner and a shop has a product, so users and products genuinely cross.
 */

/** Every figure a slice row carries, which must agree across the three. */
const TALLY_COLUMNS = [
    'delivered_orders',
    'delivered_amount',
    'shipped_orders',
    'total_shipping_fee',
    'ad_spent',
    'cod_fee',
    'cod_fee_vat',
    'total_bought_cogs',
    'total_bought_cogs_delivery_fee',
    'total_delivered_cogs',
    'gross_profit_delivered_cogs',
    'gross_profit_delivered_cogs_advisory_share',
    'gross_profit_delivered_cogs_after_advisory_share',
    'gross_profit_bought_cogs',
    'gross_profit_bought_cogs_advisory_share',
    'gross_profit_bought_cogs_after_advisory_share',
    'opex',
    'net_profit_delivered_cogs',
    'net_profit_bought_cogs',
];

/**
 * A non-gencys month where users and products cross: two products, three
 * sellers, and a seller running both products.
 */
function tally_seed($workspace): void
{
    $workspace->update(['is_gencys_partner' => false]);

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $gadget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'GADGET']);

    $widgetShop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'Widget Shop', 'product_id' => $widget->id]);
    $gadgetShop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'Gadget Shop', 'product_id' => $gadget->id]);

    $seller = function (string $name) use ($workspace) {
        $user = User::factory()->create(['name' => $name]);
        $workspace->users()->attach($user->id);

        return $user;
    };

    $ana = $seller('Ana Reyes');
    $ben = $seller('Ben Cruz');
    $cara = $seller('Cara Lim');

    $page = function (User $owner, Shop $shop, string $name) use ($workspace) {
        return Page::create([
            'workspace_id' => $workspace->id,
            'shop_id' => $shop->id,
            'name' => $name,
            'owner_id' => $owner->id,
        ]);
    };

    // Ana runs both products; Ben and Cara one each.
    $pages = [
        $page($ana, $widgetShop, 'Ana Widget'),
        $page($ana, $gadgetShop, 'Ana Gadget'),
        $page($ben, $widgetShop, 'Ben Widget'),
        $page($cara, $gadgetShop, 'Cara Gadget'),
    ];

    // Awkward amounts so the per-row COD rounding has somewhere to go.
    $amounts = [1333.33, 777.77, 2101.01, 949.49, 1234.56, 88.88, 4321.09];

    foreach ($pages as $p => $pageModel) {
        foreach ($amounts as $i => $amount) {
            PancakeOrder::create([
                'workspace_id' => $workspace->id,
                'order_number' => fake()->unique()->numerify('PC-#####'),
                'status' => 3,
                'status_name' => 'delivered',
                'shop_id' => $pageModel->shop_id,
                'page_id' => $pageModel->id,
                'customer_id' => (string) Str::uuid(),
                'inserted_at' => '2026-05-01 09:00:00',
                'final_amount' => $amount + $p,
                'delivered_at' => '2026-05-10 09:00:00',
                'shipped_at' => '2026-05-05 09:00:00',
                'shipping_fee' => 30 + $i,
            ]);
        }

        // Ad spend is per page, which is how it reaches both a user and a
        // product on this side.
        PageDailyRecord::create([
            'workspace_id' => $workspace->id,
            // Every row counts whatever wrote it — the table is unique on
            // workspace, page and date, and source is not part of that key.
            'source' => 'test',
            'page_id' => $pageModel->id,
            'page_type' => (new Page)->getMorphClass(),
            'date' => '2026-05-09',
            'ad_spent' => 111.11 * ($p + 1),
        ]);
    }

    // Goods bought, tagged to the products — shared between their sellers.
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $tagged = function (string $typeName, string $product, float $amount) use ($workspace, $account) {
        $type = TransactionType::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => $typeName],
            ['income_statement_section' => 'cost_of_sales'],
        );

        $txn = Transaction::create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'date' => '2026-05-15',
            'description' => $typeName,
            'type' => 'out',
            'transaction_type_id' => $type->id,
            'amount' => $amount,
        ]);

        TransactionProduct::create(['transaction_id' => $txn->id, 'product' => $product, 'amount' => $amount]);
    };

    $tagged('Cost of Goods', 'WIDGET', 3333.33);
    $tagged('Cost of Goods', 'GADGET', 1111.11);
    $tagged('Delivery Fee of COGS', 'WIDGET', 271.83);
}

/**
 * A saved statement carrying the workspace figures the slices close out to.
 *
 * The fees matter: the slices only pull their rounding onto a statement whose
 * own figure is within a rounding of theirs, so a statement left at zero would
 * quietly skip that step and the tests would prove nothing.
 */
function tally_statement($workspace, float $opex): IncomeStatement
{
    $from = Carbon::parse('2026-05-01')->startOfMonth();
    $totals = app(StatementOrderSourceFactory::class)
        ->for($workspace)
        ->workspaceTotals($workspace, $from, $from->copy()->endOfMonth());

    $cod = round($totals->deliveredAmount * 0.0275, 2);

    return IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'total_delivered' => $totals->deliveredAmount,
        'cod_fee' => $cod,
        'cod_fee_vat' => round($cod * 0.12, 2),
        'opex' => $opex,
        'status' => 'final',
    ]);
}

function tally_snapshot(IncomeStatement $statement): void
{
    app(UserIncomeStatementService::class)->snapshot($statement);
    app(ProductIncomeStatementService::class)->snapshot($statement);
    app(UserProductIncomeStatementService::class)->snapshot($statement);
}

test('every figure tallies across the per-user, per-product and cross slices', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    tally_seed($workspace);

    $statement = tally_statement($workspace, 7777.77);
    tally_snapshot($statement);

    $users = $statement->userStatements()->get();
    $products = $statement->productStatements()->get();
    $cross = $statement->userProductStatements()->get();

    // Guard against a vacuous pass: the slices must actually hold something,
    // and at more than one row each, or "they agree" means nothing.
    expect($users->count())->toBeGreaterThan(1)
        ->and($products->count())->toBeGreaterThan(1)
        ->and($cross->count())->toBeGreaterThan(2)
        ->and((float) $users->sum('delivered_amount'))->toBeGreaterThan(0.0)
        ->and((float) $users->sum('opex'))->toBe(7777.77);

    foreach (TALLY_COLUMNS as $column) {
        $byUser = round((float) $users->sum($column), 2);
        $byProduct = round((float) $products->sum($column), 2);
        $byBoth = round((float) $cross->sum($column), 2);

        expect($byProduct)->toBe($byUser, "per-product {$column} should equal per-user")
            ->and($byBoth)->toBe($byUser, "cross {$column} should equal per-user");
    }
});

test('a loss-making row does not throw the tally out', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    tally_seed($workspace);

    // Goods far beyond the month's revenue, so the bought basis goes negative
    // on every row — the case where a per-row advisory would stop tallying,
    // since a loss forgives the charge and regrouping changes which rows lose.
    $account = Account::where('workspace_id', $workspace->id)->first();
    $type = TransactionType::firstOrCreate(
        ['workspace_id' => $workspace->id, 'name' => 'Cost of Goods'],
        ['income_statement_section' => 'cost_of_sales'],
    );
    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-16',
        'description' => 'Bulk buy',
        'type' => 'out',
        'transaction_type_id' => $type->id,
        'amount' => 500000,
    ]);
    TransactionProduct::create(['transaction_id' => $txn->id, 'product' => 'WIDGET', 'amount' => 500000]);

    $statement = tally_statement($workspace, 1000);
    tally_snapshot($statement);

    $users = $statement->userStatements()->get();
    $products = $statement->productStatements()->get();
    $cross = $statement->userProductStatements()->get();

    expect((float) $users->sum('gross_profit_bought_cogs'))->toBeLessThan(0.0);

    foreach (['gross_profit_bought_cogs', 'gross_profit_bought_cogs_advisory_share', 'net_profit_bought_cogs', 'net_profit_delivered_cogs'] as $column) {
        expect(round((float) $products->sum($column), 2))->toBe(round((float) $users->sum($column), 2), $column)
            ->and(round((float) $cross->sum($column), 2))->toBe(round((float) $users->sum($column), 2), $column);
    }
});

test('the slices close out to the statement they belong to', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    tally_seed($workspace);

    $statement = tally_statement($workspace, 2500);
    tally_snapshot($statement);

    $users = $statement->userStatements()->get();

    // The pool and the charge come from the statement, so the rows add back to
    // exactly what it says — not to a figure of their own.
    expect(round((float) $users->sum('opex'), 2))->toBe(2500.0)
        ->and(round((float) $users->sum('cod_fee'), 2))->toBe(round((float) $statement->cod_fee, 2))
        ->and(round((float) $users->sum('cod_fee_vat'), 2))->toBe(round((float) $statement->cod_fee_vat, 2));
});

test('a stale statement is left alone rather than dragging the rows onto it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    tally_seed($workspace);

    $statement = tally_statement($workspace, 0);
    tally_snapshot($statement);

    $honest = round((float) $statement->userStatements()->sum('cod_fee'), 2);
    expect($honest)->toBeGreaterThan(1.0);

    // A statement saved under a different rate: nothing like a rounding gap.
    $statement->forceFill(['cod_fee' => 1.0, 'cod_fee_vat' => 1.0])->save();
    app(UserIncomeStatementService::class)->snapshot($statement);

    // The rows keep their own honest figure, so the mismatch stays visible
    // instead of being quietly papered over.
    expect(round((float) $statement->userStatements()->sum('cod_fee'), 2))->toBe($honest);
});
