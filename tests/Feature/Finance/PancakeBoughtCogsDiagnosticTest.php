<?php

use App\Models\Page;
use App\Models\PageDailyRecord;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionProduct;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\Finance\Services\UserProductIncomeStatementService;
use Modules\Finance\Statements\TransactionTotals;
use Modules\Pancake\Models\Order as PancakeOrder;

/**
 * A hand-checkable non-gencys month, printed step by step.
 *
 * Bought goods are tagged to a product and split between its sellers by
 * delivered orders, so the per-user figure is several steps from the ledger
 * entry. This walks every step for a case whose answer can be worked out on
 * paper, and covers the shapes that quietly go wrong: a page with no owner, a
 * shop with no product, and a product tag matching no product at all.
 *
 * (A page with no owner is not among them: `pages.owner_id` is NOT NULL, so on
 * this side every order reaches a person.)
 */
test('the non-gencys bought-COGS split can be checked by hand', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => false]);

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $gadget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'GADGET']);

    $widgetShop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'W Shop', 'product_id' => $widget->id]);
    $gadgetShop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'G Shop', 'product_id' => $gadget->id]);
    // A shop nobody linked to a product: its orders resolve to no product.
    $looseShop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'Loose Shop']);

    $seller = function (string $name) use ($workspace) {
        $user = User::factory()->create(['name' => $name]);
        $workspace->users()->attach($user->id);

        return $user;
    };

    $ana = $seller('Ana');
    $ben = $seller('Ben');

    $page = fn (User $owner, Shop $shop, string $name) => Page::create([
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
        'name' => $name,
        'owner_id' => $owner->id,
    ]);

    $anaWidget = $page($ana, $widgetShop, 'Ana W');
    $benWidget = $page($ben, $widgetShop, 'Ben W');
    $anaGadget = $page($ana, $gadgetShop, 'Ana G');
    $loosePage = $page($ana, $looseShop, 'Ana Loose');       // shop with no product

    $deliver = function (Page $p, int $count) use ($workspace) {
        foreach (range(1, $count) as $i) {
            PancakeOrder::create([
                'workspace_id' => $workspace->id,
                'order_number' => fake()->unique()->numerify('PC-#####'),
                'status' => 3,
                'status_name' => 'delivered',
                'shop_id' => $p->shop_id,
                'page_id' => $p->id,
                'customer_id' => (string) Str::uuid(),
                'inserted_at' => '2026-05-01 09:00:00',
                'final_amount' => 100,
                'delivered_at' => '2026-05-10 09:00:00',
            ]);
        }
    };

    // WIDGET delivers 9 in total: Ana 6, Ben 3.
    $deliver($anaWidget, 6);
    $deliver($benWidget, 3);
    // GADGET delivers 4, all Ana's.
    $deliver($anaGadget, 4);
    // And 2 on the shop with no product behind it.
    $deliver($loosePage, 2);

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $type = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => 'Cost of Goods',
        'income_statement_section' => 'cost_of_sales',
    ]);

    $tag = function (string $product, float $amount) use ($workspace, $account, $type) {
        $txn = Transaction::create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'date' => '2026-05-15',
            'description' => 'Cost of Goods',
            'type' => 'out',
            'transaction_type_id' => $type->id,
            'amount' => $amount,
        ]);
        TransactionProduct::create(['transaction_id' => $txn->id, 'product' => $product, 'amount' => $amount]);
    };

    $tag('WIDGET', 1000);   // 9 delivered → Ana 6/9 = 666.67, Ben 3/9 = 333.33
    $tag('GADGET', 400);    // 4 delivered, all Ana → Ana 400
    // Tagged to a name matching no product. It stays unattributed rather than
    // falling to whoever sold through the unlinked shop — a mistyped tag is not
    // their doing.
    $tag('MYSTERY', 500);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    app(UserProductIncomeStatementService::class)->snapshot($statement);
    app(UserIncomeStatementService::class)->snapshot($statement);
    app(ProductIncomeStatementService::class)->snapshot($statement);

    // --- the working, printed -------------------------------------------
    $line = str_repeat('-', 78);
    fwrite(STDERR, "\n{$line}\nTAGGED SPEND AS THE LEDGER HAS IT\n{$line}\n");
    foreach (DB::table('finance_transaction_products')->get() as $r) {
        fwrite(STDERR, sprintf("  %-12s %12s\n", $r->product, number_format((float) $r->amount, 2)));
    }

    fwrite(STDERR, "\n{$line}\nPER (USER x PRODUCT): delivered orders and the share they earn\n{$line}\n");
    fwrite(STDERR, sprintf("  %-10s %-12s %9s %12s\n", 'USER', 'PRODUCT', 'DELIVERED', 'BOUGHT COGS'));
    foreach ($statement->userProductStatements()->get() as $r) {
        fwrite(STDERR, sprintf("  %-10s %-12s %9d %12s\n",
            $r->user_name, $r->product_name, $r->delivered_orders,
            number_format((float) $r->total_bought_cogs, 2)));
    }

    fwrite(STDERR, "\n{$line}\nPER USER (must equal the rows above, summed)\n{$line}\n");
    $cross = $statement->userProductStatements()->get();
    foreach ($statement->userStatements()->get() as $r) {
        $expected = $cross->filter(fn ($c) => ($c->user_id ?? null) === ($r->user_id ?? null))
            ->sum(fn ($c) => (float) $c->total_bought_cogs);
        fwrite(STDERR, sprintf("  %-10s %12s   expected %12s  %s\n",
            $r->user_name, number_format((float) $r->total_bought_cogs, 2),
            number_format($expected, 2),
            abs((float) $r->total_bought_cogs - $expected) < 0.01 ? 'OK' : 'MISMATCH'));
    }

    fwrite(STDERR, "\n{$line}\nPER PRODUCT (must equal the rows above, summed the other way)\n{$line}\n");
    foreach ($statement->productStatements()->get() as $r) {
        $expected = $cross->filter(fn ($c) => ($c->product_id ?? null) === ($r->product_id ?? null))
            ->sum(fn ($c) => (float) $c->total_bought_cogs);
        fwrite(STDERR, sprintf("  %-14s %12s   expected %12s  %s\n",
            $r->product_name, number_format((float) $r->total_bought_cogs, 2),
            number_format($expected, 2),
            abs((float) $r->total_bought_cogs - $expected) < 0.01 ? 'OK' : 'MISMATCH'));
    }
    fwrite(STDERR, "\n");

    // --- the answers, worked out on paper --------------------------------
    $users = $statement->userStatements()->get();
    $byName = fn (string $n) => (float) $users->firstWhere('user_name', $n)?->total_bought_cogs;

    expect($byName('Ana'))->toBe(1066.67)      // 666.67 WIDGET + 400 GADGET
        ->and($byName('Ben'))->toBe(333.33)    // 3 of WIDGET's 9
        // The mistyped tag sits here, charged to nobody.
        ->and($byName('Unassigned'))->toBe(500.0)
        // Nothing invented, nothing lost: 1,000 + 400 + 500.
        ->and(round((float) $users->sum('total_bought_cogs'), 2))->toBe(1900.0);
});

test('a goods purchase with no product tag is not lost from the slices', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => false]);

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $shop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'W Shop', 'product_id' => $widget->id]);

    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);
    $page = Page::create([
        'workspace_id' => $workspace->id, 'shop_id' => $shop->id,
        'name' => 'Ana W', 'owner_id' => $ana->id,
    ]);

    PancakeOrder::create([
        'workspace_id' => $workspace->id,
        'order_number' => fake()->unique()->numerify('PC-#####'),
        'status' => 3, 'status_name' => 'delivered',
        'shop_id' => $shop->id, 'page_id' => $page->id,
        'customer_id' => (string) Str::uuid(),
        'inserted_at' => '2026-05-01 09:00:00',
        'final_amount' => 100, 'delivered_at' => '2026-05-10 09:00:00',
    ]);

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $type = TransactionType::create([
        'workspace_id' => $workspace->id, 'name' => 'Cost of Goods',
        'income_statement_section' => 'cost_of_sales',
    ]);

    // Tagged: reaches the slices. Untagged: a real purchase all the same.
    $tagged = Transaction::create([
        'workspace_id' => $workspace->id, 'account_id' => $account->id,
        'date' => '2026-05-15', 'description' => 'Tagged', 'type' => 'out',
        'transaction_type_id' => $type->id, 'amount' => 700,
    ]);
    TransactionProduct::create(['transaction_id' => $tagged->id, 'product' => 'WIDGET', 'amount' => 700]);

    Transaction::create([
        'workspace_id' => $workspace->id, 'account_id' => $account->id,
        'date' => '2026-05-16', 'description' => 'Untagged', 'type' => 'out',
        'transaction_type_id' => $type->id, 'amount' => 300,
    ]);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    app(UserIncomeStatementService::class)->snapshot($statement);
    app(ProductIncomeStatementService::class)->snapshot($statement);

    // The workspace books the whole 1,000 of Cost of Goods outflow...
    $workspaceTotal = app(TransactionTotals::class)->forWorkspace(
        $workspace,
        Carbon::parse('2026-05-01'),
        Carbon::parse('2026-05-31'),
        TransactionTotals::COST_OF_GOODS,
    );

    expect($workspaceTotal)->toBe(1000.0)
        // ...so the slices must too, with the untagged 300 sitting unresolved
        // rather than dropping out of the statement altogether.
        ->and(round((float) $statement->userStatements()->sum('total_bought_cogs'), 2))->toBe(1000.0)
        ->and(round((float) $statement->productStatements()->sum('total_bought_cogs'), 2))->toBe(1000.0);
});

test('non-gencys ad spend is the pages a seller owns, not a share of the product', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => false]);

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $shop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'W Shop', 'product_id' => $widget->id]);

    $seller = function (string $name) use ($workspace) {
        $user = User::factory()->create(['name' => $name]);
        $workspace->users()->attach($user->id);

        return $user;
    };
    $ana = $seller('Ana');
    $ben = $seller('Ben');

    $page = fn (User $owner, string $name) => Page::create([
        'workspace_id' => $workspace->id, 'shop_id' => $shop->id,
        'name' => $name, 'owner_id' => $owner->id,
    ]);
    $anaPage = $page($ana, 'Ana W');
    $benPage = $page($ben, 'Ben W');

    $deliver = function (Page $p, int $count) use ($workspace, $shop) {
        foreach (range(1, $count) as $i) {
            PancakeOrder::create([
                'workspace_id' => $workspace->id,
                'order_number' => fake()->unique()->numerify('PC-#####'),
                'status' => 3, 'status_name' => 'delivered',
                'shop_id' => $shop->id, 'page_id' => $p->id,
                'customer_id' => (string) Str::uuid(),
                'inserted_at' => '2026-05-01 09:00:00',
                'final_amount' => 100, 'delivered_at' => '2026-05-10 09:00:00',
            ]);
        }
    };

    // Deliveries run 9:1 Ana's way, but the ad money ran the other way round:
    // Ben spent nearly all of it. A delivered-order share would hand Ana 90% of
    // Ben's spending, which is exactly what must not happen.
    $deliver($anaPage, 9);
    $deliver($benPage, 1);

    $spend = fn (Page $p, float $amount) => PageDailyRecord::create([
        'workspace_id' => $workspace->id, 'source' => 'test',
        'page_id' => $p->id, 'page_type' => (new Page)->getMorphClass(),
        'date' => '2026-05-09', 'ad_spent' => $amount,
    ]);
    $spend($anaPage, 100);
    $spend($benPage, 900);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
        'status' => 'final',
    ]);
    app(UserProductIncomeStatementService::class)->snapshot($statement);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $cross = $statement->userProductStatements()->get();
    $adFor = fn (string $name) => (float) $cross->firstWhere('user_name', $name)?->ad_spent;

    // Each carries their own pages' spending, whatever their delivery share.
    expect($adFor('Ana'))->toBe(100.0)
        ->and($adFor('Ben'))->toBe(900.0);

    // Stated the other way round: a delivered-order share would have handed
    // Ana 900 of the 1,000, since she delivered nine of the ten parcels.
    expect($adFor('Ana'))->not->toBe(900.0);

    // And it still agrees with the per-user statement, which reads the same
    // page records without the product dimension.
    $users = $statement->userStatements()->get();
    expect((float) $users->firstWhere('user_name', 'Ana')->ad_spent)->toBe(100.0)
        ->and((float) $users->firstWhere('user_name', 'Ben')->ad_spent)->toBe(900.0)
        ->and(round((float) $cross->sum('ad_spent'), 2))->toBe(1000.0);
});
