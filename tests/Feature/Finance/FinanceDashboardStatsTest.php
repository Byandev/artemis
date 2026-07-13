<?php

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

function seedFinanceAccount($workspace, float $opening = 0): Account
{
    return Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Cash',
        'opening_balance' => $opening,
        'currency' => 'PHP',
        'is_active' => true,
    ]);
}

function seedTxn($workspace, Account $account, array $attrs): Transaction
{
    return Transaction::create(array_merge([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-06-15',
        'description' => 'txn',
        'amount' => 0,
    ], $attrs));
}

test('kpis endpoint sums in/out within the range, excludes transfers, and reports unreconciled', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = seedFinanceAccount($workspace);

    // In-range flows.
    seedTxn($workspace, $account, ['type' => 'in', 'transaction_type' => 'funds', 'amount' => 5000]);
    seedTxn($workspace, $account, ['type' => 'in', 'transaction_type' => 'remittance', 'amount' => 2000]);
    seedTxn($workspace, $account, ['type' => 'out', 'transaction_type' => 'expenses', 'amount' => 1500, 'running_balance' => 5500]);

    // Excluded: internal transfer + transfer fee.
    seedTxn($workspace, $account, ['type' => 'out', 'transaction_type' => 'transfer', 'amount' => 999]);
    seedTxn($workspace, $account, ['type' => 'out', 'transaction_type' => 'expenses', 'sub_category' => 'transfer_fee', 'amount' => 50]);

    // Out of range — must not be counted.
    seedTxn($workspace, $account, ['type' => 'in', 'transaction_type' => 'funds', 'amount' => 9999, 'date' => '2026-05-01']);

    // One unreconciled remittance (net_amount 1234.56).
    DB::table('finance_remittances')->insert([
        'workspace_id' => $workspace->id,
        'courier' => 'J&T',
        'soa_number' => 'SOA-1',
        'billing_date_from' => '2026-06-01',
        'billing_date_to' => '2026-06-30',
        'gross_cod' => 2000,
        'cod_fee' => 100,
        'cod_fee_vat' => 12,
        'shipping_fee' => 0,
        'return_shipping' => 0,
        'net_amount' => 1234.56,
        'status' => 'pending',
        'transaction_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/finance/dashboard/kpis?start=2026-06-01&end=2026-06-30")
        ->assertOk()
        ->assertJsonPath('cash_in', 7000)
        ->assertJsonPath('cash_out', 1500)
        ->assertJsonPath('net_cash_flow', 5500)
        ->assertJsonPath('unreconciled_count', 1)
        ->assertJsonPath('unreconciled_amount', 1234.56)
        ->assertJsonPath('range.start', '2026-06-01')
        ->assertJsonPath('range.end', '2026-06-30');
});

test('kpis endpoint is forbidden for a non-member', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->getJson("/api/workspaces/{$workspace->slug}/finance/dashboard/kpis")
        ->assertForbidden();
});

test('cash-flow endpoint buckets in/out/net within the range and excludes transfers', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = seedFinanceAccount($workspace);

    seedTxn($workspace, $account, ['type' => 'in', 'transaction_type' => 'funds', 'amount' => 3000, 'date' => '2026-06-10']);
    seedTxn($workspace, $account, ['type' => 'out', 'transaction_type' => 'expenses', 'amount' => 1000, 'date' => '2026-06-10']);
    seedTxn($workspace, $account, ['type' => 'out', 'transaction_type' => 'transfer', 'amount' => 500, 'date' => '2026-06-10']);

    $res = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/finance/dashboard/cash-flow?start=2026-06-01&end=2026-06-30")
        ->assertOk()
        ->assertJsonPath('unit', 'day');

    $point = collect($res->json('points'))->firstWhere('period', '2026-06-10');
    expect($point)->not->toBeNull()
        ->and($point['in'])->toBe(3000)
        ->and($point['out'])->toBe(1000)
        ->and($point['net'])->toBe(2000);
});

test('expense and income breakdown endpoints group by category', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = seedFinanceAccount($workspace);

    seedTxn($workspace, $account, ['type' => 'out', 'transaction_type' => 'expenses', 'amount' => 700]);
    seedTxn($workspace, $account, ['type' => 'out', 'transaction_type' => 'expenses', 'amount' => 300]);
    seedTxn($workspace, $account, ['type' => 'in', 'transaction_type' => 'funds', 'amount' => 5000]);

    $expenses = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/finance/dashboard/expense-breakdown?start=2026-06-01&end=2026-06-30")
        ->assertOk()
        ->json();

    expect($expenses)->toBe([['category' => 'expenses', 'amount' => 1000]]);

    $income = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/finance/dashboard/income-breakdown?start=2026-06-01&end=2026-06-30")
        ->assertOk()
        ->json();

    expect($income)->toBe([['category' => 'funds', 'amount' => 5000]]);
});

test('profitability endpoint combines order revenue with ad spend', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $page = Page::factory()->create([
        'id' => 700,
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
        'owner_id' => $owner->id,
    ]);

    DB::table('page_daily_budget_records')->insert([
        'workspace_id' => $workspace->id,
        'page_id' => $page->id,
        'date' => '2026-06-10',
        'budget' => 2000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pancake_orders')->insert([
        'order_number' => 'ORD-1',
        'status' => 1,
        'status_name' => 'new',
        'shop_id' => $shop->id,
        'page_id' => $page->id,
        'workspace_id' => $workspace->id,
        'customer_id' => '44444444-4444-4444-4444-444444444444',
        'total_amount' => 10000,
        'final_amount' => 8000,
        'inserted_at' => '2026-06-10 10:00:00',
    ]);

    $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/finance/dashboard/profitability?start=2026-06-01&end=2026-06-30")
        ->assertOk()
        ->assertJsonPath('revenue', 8000)
        ->assertJsonPath('ad_spend', 2000)
        ->assertJsonPath('gross_profit', 6000)
        ->assertJsonPath('margin_pct', 75)
        ->assertJsonPath('mer', 4);
});

test('reconciliation endpoint ages unreconciled remittances into buckets', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // One recent (0–7) and one very old (30+) unreconciled SOA.
    foreach ([
        ['soa' => 'SOA-NEW', 'to' => now()->subDays(2)->toDateString(), 'net' => 500],
        ['soa' => 'SOA-OLD', 'to' => now()->subDays(60)->toDateString(), 'net' => 1500],
    ] as $r) {
        DB::table('finance_remittances')->insert([
            'workspace_id' => $workspace->id,
            'courier' => 'J&T',
            'soa_number' => $r['soa'],
            'billing_date_from' => now()->subDays(90)->toDateString(),
            'billing_date_to' => $r['to'],
            'gross_cod' => $r['net'],
            'cod_fee' => 0,
            'cod_fee_vat' => 0,
            'shipping_fee' => 0,
            'return_shipping' => 0,
            'net_amount' => $r['net'],
            'status' => 'pending',
            'transaction_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $res = $this->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/finance/dashboard/reconciliation")
        ->assertOk()
        ->assertJsonPath('total_count', 2)
        ->assertJsonPath('total_amount', 2000);

    $buckets = collect($res->json('buckets'))->keyBy('label');
    expect($buckets['0–7 days']['count'])->toBe(1)
        ->and($buckets['30+ days']['count'])->toBe(1)
        ->and($buckets['30+ days']['amount'])->toBe(1500);
});
