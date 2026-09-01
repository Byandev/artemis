<?php

use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\LossCarryover;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\Finance\Services\UserProductIncomeStatementService;

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

// --- carried per person, not per product ------------------------------------

/**
 * Last month, closed: each person's own closing position, which is what this
 * month reads. Written directly — how a month reaches those figures is covered
 * above and by the statement tests.
 *
 * @param  array<int, float>  $closingByUser  user id => cumulative profit
 */
function lbf_lastMonth($workspace, string $month, array $closingByUser): IncomeStatement
{
    $statement = lbf_statement($workspace, $month, 0);

    foreach ($closingByUser as $userId => $closing) {
        $statement->userStatements()->create([
            'user_id' => $userId,
            'user_name' => 'Seller '.$userId,
            'net_profit_bought_cogs' => $closing,
            'cumulative_profit_bought_cogs' => $closing,
        ]);
    }

    return $statement;
}

function lbf_thisMonth($workspace, string $month = '2026-06'): IncomeStatement
{
    $statement = lbf_statement($workspace, $month, 0);
    app(UserIncomeStatementService::class)->snapshot($statement);

    return $statement;
}

function lbf_carryFor(IncomeStatement $statement, int $userId): float
{
    return (float) $statement->userStatements()->where('user_id', $userId)->value('loss_brought_forward');
}

test('a person carries their own closing position when it was negative', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);

    lbf_lastMonth($workspace, '2026-05', [$ana->id => -18000]);

    expect(lbf_carryFor(lbf_thisMonth($workspace), $ana->id))->toBe(18000.0);
});

test('a person who ended the month up carries nothing, whatever their products did', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);

    // Ana ran a winner and a loser and still finished ahead: WIDGET +100,000
    // against GADGET -60,000 leaves her +40,000.
    $last = lbf_lastMonth($workspace, '2026-05', [$ana->id => 40000]);

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $gadget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'GADGET']);

    foreach ([[$widget->id, 100000], [$gadget->id, -60000]] as [$productId, $net]) {
        $last->userProductStatements()->create([
            'user_id' => $ana->id, 'user_name' => 'Ana',
            'product_id' => $productId, 'product_name' => 'P',
            'net_profit_bought_cogs' => $net,
            'cumulative_profit_bought_cogs' => $net,
        ]);
    }

    // The whole point: GADGET's 60,000 already came out of what Ana earned in
    // May — it pulled her commission base from 100,000 down to 40,000. Carrying
    // it into June would charge her for it a second time.
    expect(lbf_carryFor(lbf_thisMonth($workspace), $ana->id))->toBe(0.0);
});

test('a product cannot be tagged with a deficit at all', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);
    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);

    $statement = lbf_statement($workspace, '2026-06', 0);

    // Refused outright rather than quietly stored against the person: a caller
    // still sending a product is working from the old idea and should hear so.
    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/loss-carryovers",
        ['user_id' => $ana->id, 'product_id' => $widget->id, 'amount' => 500],
    )->assertSessionHasErrors('product_id');

    expect(LossCarryover::count())->toBe(0);
});

test('a typed figure wins for the month it is entered against', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);

    // Last month says -5,000, but the books before this system say otherwise.
    lbf_lastMonth($workspace, '2026-05', [$ana->id => -5000]);
    $june = lbf_statement($workspace, '2026-06', 0);

    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$june->id}/loss-carryovers",
        ['user_id' => $ana->id, 'amount' => 12345.67],
    )->assertRedirect();

    expect(lbf_carryFor($june, $ana->id))->toBe(12345.67);
});

test('clearing a typed figure hands the month back to the worked-out one', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);

    lbf_lastMonth($workspace, '2026-05', [$ana->id => -5000]);
    $june = lbf_statement($workspace, '2026-06', 0);
    $url = "/workspaces/{$workspace->slug}/finance/income-statements/{$june->id}/loss-carryovers";

    $this->actingAs($owner)->post($url, ['user_id' => $ana->id, 'amount' => 999]);
    expect(lbf_carryFor($june, $ana->id))->toBe(999.0);

    $this->actingAs($owner)->post($url, ['user_id' => $ana->id, 'amount' => 0]);

    // Not nought — the entry is gone, so last month speaks again.
    expect(LossCarryover::count())->toBe(0)
        ->and(lbf_carryFor($june, $ana->id))->toBe(5000.0);
});

test('a typed figure survives a regenerate, unlike the rows that carry it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);

    $june = lbf_statement($workspace, '2026-06', 0);

    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$june->id}/loss-carryovers",
        ['user_id' => $ana->id, 'amount' => 777.77],
    )->assertRedirect();

    $this->actingAs($owner)->post(
        "/workspaces/{$workspace->slug}/finance/income-statements/{$june->id}/regenerate",
    )->assertRedirect();

    // A regenerate deletes and rebuilds every slice row, which is why the
    // entries live in a table of their own.
    expect(lbf_carryFor($june->fresh(), $ana->id))->toBe(777.77);
});

test('the figure reaches the pages that show it, and none that should not', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);

    lbf_lastMonth($workspace, '2026-05', [$ana->id => -2500]);
    $june = lbf_thisMonth($workspace);
    app(ProductIncomeStatementService::class)->snapshot($june);
    app(UserProductIncomeStatementService::class)->snapshot($june);

    // The per-user page states it, and says it was worked out rather than typed.
    $users = app(UserIncomeStatementService::class)->payload($june);
    $row = collect($users['users'])->firstWhere('user_id', $ana->id);

    expect($row['loss_brought_forward'])->toBe(2500.0)
        ->and($row['loss_brought_forward_entered'])->toBeFalse();

    // A product carries none of it — it is a person's, and a product is run by
    // several people.
    foreach ($june->productStatements as $product) {
        expect((float) $product->loss_brought_forward)->toBe(0.0);
    }

    // Nor does any one of that person's products.
    foreach ($june->userProductStatements as $pair) {
        expect((float) $pair->loss_brought_forward)->toBe(0.0);
    }
});

test('the drill-down shows the person’s deficit on its total, not against a product', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $ana = User::factory()->create(['name' => 'Ana']);
    $workspace->users()->attach($ana->id);

    lbf_lastMonth($workspace, '2026-05', [$ana->id => -3200]);

    $june = lbf_statement($workspace, '2026-06', 0);
    // A row for Ana so the drill-down has something to open.
    $june->userProductStatements()->create([
        'user_id' => $ana->id, 'user_name' => 'Ana',
        'product_id' => null, 'product_name' => 'Unresolved',
        'net_profit_bought_cogs' => 1000,
    ]);

    $payload = app(UserProductIncomeStatementService::class)
        ->userPayload($june, $ana->id);

    expect($payload['total']['loss_brought_forward'])->toBe(3200.0)
        // Their products each carry none of it.
        ->and(collect($payload['products'])->pluck('loss_brought_forward')->unique()->all())->toBe([0.0]);
});
