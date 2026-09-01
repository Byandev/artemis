<?php

use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Str;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\LossCarryover;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\Finance\Services\UserProductIncomeStatementService;
use Modules\Pancake\Models\Order;

/**
 * A month that ends in the red is carried into the next one.
 *
 * The figure that carries is the previous month's *cumulative* profit, not its
 * net profit, so a run of bad months accumulates instead of each one forgiving
 * everything before it. These cover that chain, and the cases where nothing
 * should carry at all.
 *
 * The statements are written directly: how a month arrives at its net profit is
 * IncomeStatementTest's business, and this file is only about what happens
 * between one month and the next.
 */
function lbf_statement($workspace, string $month, float $netDelivered, ?float $netBought = null): IncomeStatement
{
    return IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => $month.'-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'net_profit_delivered_cogs' => $netDelivered,
        'net_profit_bought_cogs' => $netBought ?? $netDelivered,
        'status' => 'final',
    ]);
}

/** Close a month the way the controller does, carrying from the one before. */
function lbf_close($workspace, IncomeStatement $statement): IncomeStatement
{
    $previous = IncomeStatement::where('workspace_id', $workspace->id)
        ->whereDate('period_month', $statement->period_month->copy()->subMonthNoOverflow()->startOfMonth())
        ->first();

    $carried = [];

    foreach (['delivered_cogs', 'bought_cogs'] as $basis) {
        $before = (float) ($previous?->{"cumulative_profit_{$basis}"} ?? 0.0);
        $loss = $before < 0 ? round(abs($before), 2) : 0.0;

        $carried["loss_brought_forward_{$basis}"] = $loss;
        $carried["cumulative_profit_{$basis}"] = round(
            (float) $statement->{"net_profit_{$basis}"} - $loss, 2);
    }

    $statement->forceFill($carried)->save();

    return $statement->fresh();
}

test('a losing month is carried into the next one', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $january = lbf_close($workspace, lbf_statement($workspace, '2026-01', -50000));

    // Nothing before it, so January carries nothing in and stands as it is.
    expect((float) $january->loss_brought_forward_delivered_cogs)->toBe(0.0)
        ->and((float) $january->cumulative_profit_delivered_cogs)->toBe(-50000.0);

    // February earns 30,000 but starts 50,000 in the hole.
    $february = lbf_close($workspace, lbf_statement($workspace, '2026-02', 30000));

    expect((float) $february->loss_brought_forward_delivered_cogs)->toBe(50000.0)
        ->and((float) $february->cumulative_profit_delivered_cogs)->toBe(-20000.0);
});

test('a run of losing months adds up rather than reaching back only one', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    lbf_close($workspace, lbf_statement($workspace, '2026-01', -40000));
    lbf_close($workspace, lbf_statement($workspace, '2026-02', -25000));
    $march = lbf_close($workspace, lbf_statement($workspace, '2026-03', 10000));

    // February ends at -65,000 (its own -25,000 on January's -40,000), and
    // that whole hole is what March has to fill — not just February's own loss.
    expect((float) $march->loss_brought_forward_delivered_cogs)->toBe(65000.0)
        ->and((float) $march->cumulative_profit_delivered_cogs)->toBe(-55000.0);
});

test('a profitable month carries nothing forward', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    lbf_close($workspace, lbf_statement($workspace, '2026-01', 80000));
    $february = lbf_close($workspace, lbf_statement($workspace, '2026-02', 20000));

    // A good month is not a credit against a bad one — it has already been
    // taken, so February starts level rather than 80,000 ahead.
    expect((float) $february->loss_brought_forward_delivered_cogs)->toBe(0.0)
        ->and((float) $february->cumulative_profit_delivered_cogs)->toBe(20000.0);
});

test('the hole closes once a month more than fills it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    lbf_close($workspace, lbf_statement($workspace, '2026-01', -15000));
    $february = lbf_close($workspace, lbf_statement($workspace, '2026-02', 40000));
    $march = lbf_close($workspace, lbf_statement($workspace, '2026-03', 5000));

    expect((float) $february->cumulative_profit_delivered_cogs)->toBe(25000.0)
        // February ended in profit, so March starts level again.
        ->and((float) $march->loss_brought_forward_delivered_cogs)->toBe(0.0)
        ->and((float) $march->cumulative_profit_delivered_cogs)->toBe(5000.0);
});

test('the two cost-of-goods bases keep their own history', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    // January is in profit on the delivered basis and in the red on the bought
    // one — a month where stock was bought well beyond what sold.
    lbf_close($workspace, lbf_statement($workspace, '2026-01', 20000, -60000));
    $february = lbf_close($workspace, lbf_statement($workspace, '2026-02', 10000, 10000));

    expect((float) $february->loss_brought_forward_delivered_cogs)->toBe(0.0)
        ->and((float) $february->cumulative_profit_delivered_cogs)->toBe(10000.0)
        // The bought basis carries its own hole, and must not borrow the
        // delivered side's good month.
        ->and((float) $february->loss_brought_forward_bought_cogs)->toBe(60000.0)
        ->and((float) $february->cumulative_profit_bought_cogs)->toBe(-50000.0);
});

test('a gap in the months carries nothing across it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    lbf_close($workspace, lbf_statement($workspace, '2026-01', -30000));
    // No February statement was ever closed.
    $march = lbf_close($workspace, lbf_statement($workspace, '2026-03', 5000));

    // Only the month immediately before is looked at, so a skipped month
    // breaks the chain. Worth knowing: closing February later would change
    // March, and March would need regenerating to pick it up.
    expect((float) $march->loss_brought_forward_delivered_cogs)->toBe(0.0)
        ->and((float) $march->cumulative_profit_delivered_cogs)->toBe(5000.0);
});

test('one workspace never carries another workspace’s loss', function () {
    ['workspace' => $mine] = makeWorkspaceWithOwner();
    ['workspace' => $theirs] = makeWorkspaceWithOwner();

    lbf_close($theirs, lbf_statement($theirs, '2026-01', -90000));
    lbf_close($mine, lbf_statement($mine, '2026-01', 1000));

    $february = lbf_close($mine, lbf_statement($mine, '2026-02', 2000));

    expect((float) $february->loss_brought_forward_delivered_cogs)->toBe(0.0)
        ->and((float) $february->cumulative_profit_delivered_cogs)->toBe(2000.0);
});

test('a saved statement carries the loss through the controller', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    // Last month closed in the red.
    lbf_close($workspace, lbf_statement($workspace, '2026-04', -12345.67));

    // This month is generated for real, through the save path.
    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/finance/income-statements", [
            'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
        ])
        ->assertRedirect();

    $may = IncomeStatement::where('workspace_id', $workspace->id)
        ->whereDate('period_month', '2026-05-01')->first();

    expect((float) $may->loss_brought_forward_delivered_cogs)->toBe(12345.67)
        ->and((float) $may->cumulative_profit_delivered_cogs)
        ->toBe(round((float) $may->net_profit_delivered_cogs - 12345.67, 2));
});

// --- a figure typed in rather than read off last month ----------------------

function lbf_url($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/income-statements{$path}";
}

test('a typed figure is used when no previous month was ever closed', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    // Nothing before this month exists here — it was closed on a spreadsheet.
    $this->actingAs($user)
        ->post(lbf_url($workspace), [
            'month' => '2026-05',
            'cod_rate' => 0.02,
            'vat_rate' => 0.12,
            'loss_brought_forward' => 47500.25,
        ])
        ->assertRedirect();

    $may = IncomeStatement::where('workspace_id', $workspace->id)->first();

    expect((float) $may->manual_loss_brought_forward)->toBe(47500.25)
        ->and((float) $may->loss_brought_forward_delivered_cogs)->toBe(47500.25)
        // One figure, applied to both bases — the sheet knew one bottom line.
        ->and((float) $may->loss_brought_forward_bought_cogs)->toBe(47500.25)
        ->and((float) $may->cumulative_profit_delivered_cogs)
        ->toBe(round((float) $may->net_profit_delivered_cogs - 47500.25, 2));
});

test('a typed figure beats the previous month rather than adding to it', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    // A previous month does exist, and it lost money...
    lbf_close($workspace, lbf_statement($workspace, '2026-04', -80000));

    // ...but someone states the figure themselves, having reconciled it.
    $this->actingAs($user)
        ->post(lbf_url($workspace), [
            'month' => '2026-05',
            'cod_rate' => 0.02,
            'vat_rate' => 0.12,
            'loss_brought_forward' => 1000,
        ])
        ->assertRedirect();

    $may = IncomeStatement::where('workspace_id', $workspace->id)
        ->whereDate('period_month', '2026-05-01')->first();

    // What was typed wins outright; the 80,000 is not added to it.
    expect((float) $may->loss_brought_forward_delivered_cogs)->toBe(1000.0);
});

test('a typed zero states the month broke even and is kept', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    lbf_close($workspace, lbf_statement($workspace, '2026-04', -80000));

    $this->actingAs($user)
        ->post(lbf_url($workspace), [
            'month' => '2026-05',
            'cod_rate' => 0.02,
            'vat_rate' => 0.12,
            'loss_brought_forward' => 0,
        ])
        ->assertRedirect();

    $may = IncomeStatement::where('workspace_id', $workspace->id)
        ->whereDate('period_month', '2026-05-01')->first();

    // Nought given is a statement, not an absence: April's loss is not used.
    expect((float) $may->manual_loss_brought_forward)->toBe(0.0)
        ->and((float) $may->loss_brought_forward_delivered_cogs)->toBe(0.0);
});

test('omitting the figure falls back to the previous month', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    lbf_close($workspace, lbf_statement($workspace, '2026-04', -33000));

    $this->actingAs($user)
        ->post(lbf_url($workspace), [
            'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
        ])
        ->assertRedirect();

    $may = IncomeStatement::where('workspace_id', $workspace->id)
        ->whereDate('period_month', '2026-05-01')->first();

    expect($may->manual_loss_brought_forward)->toBeNull()
        ->and((float) $may->loss_brought_forward_delivered_cogs)->toBe(33000.0);
});

test('a typed figure survives a regenerate', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    $this->actingAs($user)
        ->post(lbf_url($workspace), [
            'month' => '2026-05',
            'cod_rate' => 0.02,
            'vat_rate' => 0.12,
            'loss_brought_forward' => 9876.54,
        ])
        ->assertRedirect();

    $may = IncomeStatement::where('workspace_id', $workspace->id)->first();

    $this->actingAs($user)
        ->post(lbf_url($workspace, "/{$may->id}/regenerate"))
        ->assertRedirect();

    // Rebuilding this month tells us nothing new about the months before it,
    // so what someone stated about them has to hold.
    expect((float) $may->fresh()->manual_loss_brought_forward)->toBe(9876.54)
        ->and((float) $may->fresh()->loss_brought_forward_delivered_cogs)->toBe(9876.54);
});

test('a negative figure is refused — it is an amount owing, not a profit', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($user)
        ->post(lbf_url($workspace), [
            'month' => '2026-05',
            'cod_rate' => 0.02,
            'vat_rate' => 0.12,
            'loss_brought_forward' => -500,
        ])
        ->assertSessionHasErrors('loss_brought_forward');
});

// --- entered per seller and product, added up from there --------------------

/** A month with two sellers on one product, and one on another. */
function lbf_seed($workspace): array
{
    $workspace->update(['is_gencys_partner' => false]);

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $gadget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'GADGET']);
    $wShop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'W', 'product_id' => $widget->id]);
    $gShop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'G', 'product_id' => $gadget->id]);

    $seller = function (string $name) use ($workspace) {
        $user = User::factory()->create(['name' => $name]);
        $workspace->users()->attach($user->id);

        return $user;
    };
    $ana = $seller('Ana');
    $ben = $seller('Ben');

    $page = fn ($owner, $shop, $name) => Page::create([
        'workspace_id' => $workspace->id, 'shop_id' => $shop->id,
        'name' => $name, 'owner_id' => $owner->id,
    ]);

    $deliver = function ($page, $shop) use ($workspace) {
        Order::create([
            'workspace_id' => $workspace->id,
            'order_number' => fake()->unique()->numerify('PC-######'),
            'status' => 3, 'status_name' => 'delivered',
            'shop_id' => $shop->id, 'page_id' => $page->id,
            'customer_id' => (string) Str::uuid(),
            'inserted_at' => '2026-05-01 09:00:00',
            'final_amount' => 1000, 'delivered_at' => '2026-05-10 09:00:00',
        ]);
    };

    $deliver($page($ana, $wShop, 'Ana W'), $wShop);
    $deliver($page($ben, $wShop, 'Ben W'), $wShop);
    $deliver($page($ana, $gShop, 'Ana G'), $gShop);

    return ['ana' => $ana, 'ben' => $ben, 'widget' => $widget, 'gadget' => $gadget];
}

test('a loss entered per seller and product adds up to every level above it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['ana' => $ana, 'ben' => $ben, 'widget' => $widget, 'gadget' => $gadget] = lbf_seed($workspace);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $url = "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/loss-carryovers";

    // Three entries at the finest grain.
    $this->actingAs($owner)->post($url, ['user_id' => $ana->id, 'product_id' => $widget->id, 'amount' => 100])->assertRedirect();
    $this->actingAs($owner)->post($url, ['user_id' => $ben->id, 'product_id' => $widget->id, 'amount' => 250])->assertRedirect();
    $this->actingAs($owner)->post($url, ['user_id' => $ana->id, 'product_id' => $gadget->id, 'amount' => 40])->assertRedirect();

    $cross = $statement->userProductStatements()->get();
    $pair = fn ($u, $p) => (float) $cross->first(fn ($r) => $r->user_id === $u && $r->product_id === $p)?->loss_brought_forward;

    // The entries themselves.
    expect($pair($ana->id, $widget->id))->toBe(100.0)
        ->and($pair($ben->id, $widget->id))->toBe(250.0)
        ->and($pair($ana->id, $gadget->id))->toBe(40.0);

    // Added up across products for a person...
    $users = $statement->userStatements()->get();
    expect((float) $users->firstWhere('user_id', $ana->id)->loss_brought_forward)->toBe(140.0)
        ->and((float) $users->firstWhere('user_id', $ben->id)->loss_brought_forward)->toBe(250.0);

    // ...across people for a product...
    $products = $statement->productStatements()->get();
    expect((float) $products->firstWhere('product_id', $widget->id)->loss_brought_forward)->toBe(350.0)
        ->and((float) $products->firstWhere('product_id', $gadget->id)->loss_brought_forward)->toBe(40.0);

    // ...and every way round it comes to the same 390.
    expect(round((float) $cross->sum('loss_brought_forward'), 2))->toBe(390.0)
        ->and(round((float) $users->sum('loss_brought_forward'), 2))->toBe(390.0)
        ->and(round((float) $products->sum('loss_brought_forward'), 2))->toBe(390.0);
});

test('cumulative profit on a row is its net profit less what it carried', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['ana' => $ana, 'widget' => $widget] = lbf_seed($workspace);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/loss-carryovers",
        ['user_id' => $ana->id, 'product_id' => $widget->id, 'amount' => 500],
    )->assertRedirect();

    $row = $statement->userProductStatements()
        ->where('user_id', $ana->id)->where('product_id', $widget->id)->first();

    expect((float) $row->loss_brought_forward)->toBe(500.0)
        ->and((float) $row->cumulative_profit_bought_cogs)
        ->toBe(round((float) $row->net_profit_bought_cogs - 500, 2));
});

test('an entry of nought clears it rather than storing a zero', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['ana' => $ana, 'widget' => $widget] = lbf_seed($workspace);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
        'status' => 'final',
    ]);
    $url = "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/loss-carryovers";

    $this->actingAs($owner)->post($url, ['user_id' => $ana->id, 'product_id' => $widget->id, 'amount' => 300]);
    expect(LossCarryover::count())->toBe(1);

    $this->actingAs($owner)->post($url, ['user_id' => $ana->id, 'product_id' => $widget->id, 'amount' => 0]);

    // Nothing carried and a carryover of nothing are the same thing, so the
    // month doesn't read as edited when it isn't.
    expect(LossCarryover::count())->toBe(0)
        ->and((float) $statement->userProductStatements()
            ->where('user_id', $ana->id)->value('loss_brought_forward'))->toBe(0.0);
});

test('entries survive a regenerate, unlike the rows that carry them', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['ana' => $ana, 'widget' => $widget] = lbf_seed($workspace);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/loss-carryovers",
        ['user_id' => $ana->id, 'product_id' => $widget->id, 'amount' => 777.77],
    )->assertRedirect();

    // A regenerate deletes and rebuilds every slice row, which is exactly why
    // the entries are kept in their own table.
    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/regenerate",
    )->assertRedirect();

    expect((float) $statement->userProductStatements()
        ->where('user_id', $ana->id)->where('product_id', $widget->id)
        ->value('loss_brought_forward'))->toBe(777.77)
        // And the month's own figure is those entries added up.
        ->and((float) $statement->fresh()->loss_brought_forward_bought_cogs)->toBe(777.77);
});

test('the entered figure comes back in the payloads the pages read', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['ana' => $ana, 'widget' => $widget] = lbf_seed($workspace);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/loss-carryovers",
        ['user_id' => $ana->id, 'product_id' => $widget->id, 'amount' => 321.45],
    )->assertRedirect();

    // Stored is not enough: the figure has to reach the page, or the box it was
    // typed into reads empty again on the next load.
    $cross = app(UserProductIncomeStatementService::class)->payload($statement);
    $column = collect($cross['rows'])->first(
        fn ($r) => $r['user_id'] === $ana->id && $r['product_id'] === $widget->id,
    );

    expect($column)->toHaveKey('loss_brought_forward')
        ->and($column['loss_brought_forward'])->toBe(321.45)
        ->and($column)->toHaveKey('cumulative_profit_bought_cogs');

    // And on the pages above, where it is read-only.
    $users = app(UserIncomeStatementService::class)->payload($statement);
    $products = app(ProductIncomeStatementService::class)->payload($statement);

    expect(collect($users['users'])->firstWhere('user_id', $ana->id)['loss_brought_forward'])->toBe(321.45)
        ->and(collect($products['products'])->firstWhere('product_id', $widget->id)['loss_brought_forward'])->toBe(321.45);
});

test('the drill-down payload carries it too', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['ana' => $ana, 'widget' => $widget] = lbf_seed($workspace);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.0275, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/loss-carryovers",
        ['user_id' => $ana->id, 'product_id' => $widget->id, 'amount' => 55.5],
    )->assertRedirect();

    $payload = app(UserProductIncomeStatementService::class)
        ->userPayload($statement, $ana->id);

    expect(collect($payload['products'])->firstWhere('product_id', $widget->id)['loss_brought_forward'])
        ->toBe(55.5);
});
