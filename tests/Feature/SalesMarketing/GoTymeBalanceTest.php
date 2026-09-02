<?php

use App\Enums\Permission;
use App\Models\User;
use Modules\Finance\Enums\WalletType;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;

function enableSalesMarketing($workspace): void
{
    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);
}

function walletPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Juan Dela Cruz',
        'wallet_type' => 'main',
        'opening_balance' => '1500.50',
        'currency' => 'php',
        'notes' => 'Rider float',
        'is_active' => true,
    ], $overrides);
}

it('404s the page while the S&M module is off', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertNotFound();
});

it('renders the page for an owner when the module is on', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk();
});

it('stores an account flagged as a user wallet', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $this->post("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance", walletPayload())
        ->assertRedirect("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance");

    $account = Account::where('workspace_id', $workspace->id)->sole();

    expect($account->is_user_wallet)->toBeTrue()
        ->and($account->name)->toBe('Juan Dela Cruz')
        ->and($account->wallet_type)->toBe(WalletType::Main)
        ->and($account->currency)->toBe('PHP')
        ->and((float) $account->opening_balance)->toBe(1500.50);
});

it('stores a backup wallet when that type is chosen', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $this->post(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance",
        walletPayload(['wallet_type' => 'backup']),
    )->assertRedirect();

    expect(Account::where('workspace_id', $workspace->id)->sole()->wallet_type)
        ->toBe(WalletType::Backup);
});

it('rejects a wallet type outside main and backup', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $this->post(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance",
        walletPayload(['wallet_type' => 'tertiary']),
    )->assertSessionHasErrors('wallet_type');

    expect(Account::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('requires a wallet type', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $payload = walletPayload();
    unset($payload['wallet_type']);

    $this->post("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance", $payload)
        ->assertSessionHasErrors('wallet_type');
});

it('leaves wallet_type null on accounts created from Finance → Accounts', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->post("/workspaces/{$workspace->slug}/finance/accounts", [
        'name' => 'Company BPI',
        'opening_balance' => '0',
        'currency' => 'PHP',
        'wallet_type' => 'main', // ignored: not an input on that form
    ])->assertRedirect();

    expect(Account::where('workspace_id', $workspace->id)->sole()->wallet_type)->toBeNull();
});

it('keeps user wallets out of the finance accounts listing', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Company BPI',
        'opening_balance' => 0,
        'currency' => 'PHP',
    ]);
    Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Wallet — Juan',
        'opening_balance' => 0,
        'currency' => 'PHP',
        'is_user_wallet' => true,
    ]);

    $this->get("/workspaces/{$workspace->slug}/finance/accounts")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accounts.data', 1)
            ->where('accounts.data.0.name', 'Company BPI'));
});

it('lists only the user wallets on the Go Tyme Balance page', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Company BPI',
        'opening_balance' => 0,
        'currency' => 'PHP',
    ]);
    Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Wallet — Juan',
        'opening_balance' => 250,
        'currency' => 'PHP',
        'is_user_wallet' => true,
        'wallet_type' => WalletType::Backup,
    ]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Wallet — Juan')
            ->where('rows.0.is_user_wallet', true)
            ->where('rows.0.wallet_type', 'backup'));
});

it('does not flag accounts created from Finance → Accounts as wallets', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->post("/workspaces/{$workspace->slug}/finance/accounts", [
        'name' => 'Company BPI',
        'opening_balance' => '0',
        'currency' => 'PHP',
        'is_user_wallet' => true, // ignored: not a fillable input on that form
    ])->assertRedirect();

    expect(Account::where('workspace_id', $workspace->id)->sole()->is_user_wallet)
        ->toBeFalse();
});

it('lets a viewer read the page but not create an account', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    enableSalesMarketing($workspace);

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewGoTymeBalance->value],
        'Sales & Marketing',
    );

    $this->actingAs($viewer)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance", walletPayload())
        ->assertForbidden();

    expect(Account::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('lets a member holding the manage grant create an account', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    enableSalesMarketing($workspace);

    $manager = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewGoTymeBalance->value, Permission::ManageGoTymeBalance->value],
        'Sales & Marketing',
    );

    $this->actingAs($manager)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance", walletPayload())
        ->assertRedirect();

    expect(Account::where('workspace_id', $workspace->id)->sole()->is_user_wallet)->toBeTrue();
});

it('does not let a non-member reach the page', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    enableSalesMarketing($workspace);

    $this->actingAs(User::factory()->create())
        ->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertForbidden();
});

it('keeps user wallets out of the finance transaction account picker', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Company BPI',
        'opening_balance' => 0,
        'currency' => 'PHP',
    ]);
    Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Wallet — Juan',
        'opening_balance' => 0,
        'currency' => 'PHP',
        'is_user_wallet' => true,
        'wallet_type' => WalletType::Main,
    ]);

    $this->get("/workspaces/{$workspace->slug}/finance/transactions/create")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Company BPI'));
});

it('rejects a finance transaction posted against a user wallet', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $wallet = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Wallet — Juan',
        'opening_balance' => 0,
        'currency' => 'PHP',
        'is_user_wallet' => true,
        'wallet_type' => WalletType::Main,
    ]);

    $this->post("/workspaces/{$workspace->slug}/finance/transactions", [
        'account_id' => $wallet->id,
        'date' => now()->toDateString(),
        'type' => 'in',
        'amount' => '100',
        'description' => 'Should not post',
    ])->assertSessionHasErrors('account_id');

    expect(Transaction::where('workspace_id', $workspace->id)->count())
        ->toBe(0);
});

function makeWallet($workspace, array $overrides = []): Account
{
    return Account::create(array_merge([
        'workspace_id' => $workspace->id,
        'name' => 'Wallet — Juan',
        'opening_balance' => 0,
        'currency' => 'PHP',
        'is_user_wallet' => true,
        'wallet_type' => WalletType::Main,
    ], $overrides));
}

function makeLedgerType($workspace, string $name = 'Ad Spent'): TransactionType
{
    return TransactionType::firstOrCreate([
        'workspace_id' => $workspace->id,
        'name' => $name,
    ]);
}

function entryPayload($workspace, array $overrides = []): array
{
    return array_merge([
        'date' => '2025-06-04',
        'transaction_type_id' => makeLedgerType($workspace)->id,
        'description' => 'FB ADS',
        'department' => 'Marketing',
        'type' => 'out',
        'amount' => '3114.67',
    ], $overrides);
}

function makeEntry(Account $wallet, array $overrides = []): Transaction
{
    return Transaction::create(array_merge([
        'workspace_id' => $wallet->workspace_id,
        'account_id' => $wallet->id,
        'date' => '2025-06-04',
        'description' => 'FB ADS',
        'department' => 'Marketing',
        'type' => 'out',
        'amount' => 100,
    ], $overrides));
}

it('lays out a trailing eight-day window ending today', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWallet($workspace);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dates', 8)
            ->where('dates.0.date', now()->subDays(7)->toDateString())
            ->where('dates.7.date', now()->toDateString())
            ->where('dates.7.is_today', true)
            ->where('dates.0.is_today', false));
});

it('honours a hand-picked date range', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWallet($workspace);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-05")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dates', 5)
            ->where('dates.0.date', '2025-06-01')
            ->where('dates.4.date', '2025-06-05')
            // The window no longer reaches today, so no column is derived.
            ->where('dates.4.is_today', false)
            ->where('query.start', '2025-06-01')
            ->where('query.end', '2025-06-05'));
});

it('reverses a range handed back to front', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWallet($workspace);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-05&end=2025-06-01")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('query.start', '2025-06-01')
            ->where('query.end', '2025-06-05'));
});

it('clamps a range that would ask for years of columns', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWallet($workspace);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2020-01-01&end=2025-06-30")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dates', 92)
            ->where('query.end', '2025-06-30')
            ->where('query.start', '2025-03-31'));
});

it('ignores an unparseable range and falls back to the default window', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWallet($workspace);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=nonsense&end=")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dates', 8)
            ->where('query.end', now()->toDateString()));
});

/**
 * A wallet already open before the fixed windows the grid tests use, so the
 * assertions read balances rather than the blanks that precede a wallet.
 */
function makeWalletOpenedOn($workspace, string $date, array $overrides = []): Account
{
    $wallet = makeWallet($workspace, $overrides);

    $wallet->forceFill(['created_at' => "{$date} 00:00:00"])->save();

    return $wallet->refresh();
}

it('leaves the days before a wallet was opened blank, not zero', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWalletOpenedOn($workspace, '2025-06-03', ['opening_balance' => 100]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-04")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.opened_on', '2025-06-03')
            ->where('rows.0.days.2025-06-01', null)
            ->where('rows.0.days.2025-06-02', null)
            // Opened on the 3rd, and reading from that day on.
            ->where('rows.0.days.2025-06-03', 100)
            ->where('rows.0.days.2025-06-04', 100));
});

it('totals a column as null while no wallet was open on it', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWalletOpenedOn($workspace, '2025-06-02', ['opening_balance' => 100]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-02")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.2025-06-01', null)
            ->where('totals.2025-06-02', 100));
});

it('fills every column from the opening balance while the ledger is empty', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-03")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows.0.days', 3)
            ->where('rows.0.days.2025-06-01', 100)
            ->where('rows.0.days.2025-06-02', 100)
            ->where('rows.0.days.2025-06-03', 100));
});

it('walks each column to the wallet\'s balance at the close of that day', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);

    makeEntry($wallet, ['date' => '2025-06-02', 'type' => 'in', 'amount' => 1600]);
    makeEntry($wallet, ['date' => '2025-06-04', 'type' => 'out', 'amount' => 200]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-05")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Opening balance, then each entry's day, then the figure it left
            // behind carried across the days that moved nothing.
            ->where('rows.0.days.2025-06-01', 100)
            ->where('rows.0.days.2025-06-02', 1700)
            ->where('rows.0.days.2025-06-03', 1700)
            ->where('rows.0.days.2025-06-04', 1500)
            ->where('rows.0.days.2025-06-05', 1500));
});

it('sums a day that carries more than one entry', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);

    makeEntry($wallet, ['date' => '2025-06-02', 'type' => 'in', 'amount' => 500]);
    makeEntry($wallet, ['date' => '2025-06-02', 'type' => 'out', 'amount' => 120]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-02")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.days.2025-06-01', 100)
            ->where('rows.0.days.2025-06-02', 480));
});

it('carries everything posted before the window into its first column', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);

    makeEntry($wallet, ['date' => '2025-05-30', 'type' => 'in', 'amount' => 500]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-02")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.days.2025-06-01', 600)
            ->where('rows.0.days.2025-06-02', 600));
});

it('leaves entries posted after the window out of it', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);

    makeEntry($wallet, ['date' => '2025-06-10', 'type' => 'in', 'amount' => 999]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-02")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.days.2025-06-01', 100)
            ->where('rows.0.days.2025-06-02', 100));
});

it('re-flows the grid when an entry is back-dated in', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance";

    $this->post("{$url}/{$wallet->id}/entries", entryPayload($workspace, [
        'date' => '2025-06-04', 'type' => 'in', 'amount' => '50',
    ]))->assertRedirect();

    $this->post("{$url}/{$wallet->id}/entries", entryPayload($workspace, [
        'date' => '2025-06-02', 'type' => 'in', 'amount' => '10',
    ]))->assertRedirect();

    $this->get("{$url}?start=2025-06-01&end=2025-06-04")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.days.2025-06-01', 100)
            ->where('rows.0.days.2025-06-02', 110)
            ->where('rows.0.days.2025-06-03', 110)
            ->where('rows.0.days.2025-06-04', 160));
});

it('drops a deleted entry back out of the grid', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);
    $entry = makeEntry($wallet, ['date' => '2025-06-02', 'type' => 'in', 'amount' => 500]);

    $this->delete("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries/{$entry->id}")
        ->assertRedirect();

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-02")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.0.days.2025-06-02', 100));
});

it('shows the wallet\'s live ledger balance in the today column', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['opening_balance' => 0]);
    $today = now()->toDateString();

    makeEntry($wallet, ['type' => 'in', 'amount' => 500]);
    makeEntry($wallet, ['type' => 'out', 'amount' => 120]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where("rows.0.days.{$today}", 380)
            ->where("totals.{$today}", 380));
});

it('sits a wallet with no ledger at its opening balance in the today column', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWallet($workspace, ['opening_balance' => 250]);
    $today = now()->toDateString();

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where("rows.0.days.{$today}", 250));
});

it('totals each column across every wallet, opening balances included', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $first = makeWalletOpenedOn($workspace, '2025-05-01', ['name' => 'Wallet A', 'opening_balance' => 100.25]);
    makeWalletOpenedOn($workspace, '2025-05-01', ['name' => 'Wallet B', 'opening_balance' => 50.80]);

    makeEntry($first, ['date' => '2025-06-02', 'type' => 'in', 'amount' => 20]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-02")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.2025-06-01', 151.05)
            ->where('totals.2025-06-02', 171.05));
});

it('keeps another workspace\'s wallets out of the grid', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);

    ['workspace' => $other] = makeWorkspaceWithOwner();
    makeWalletOpenedOn($other, '2025-05-01', ['opening_balance' => 999]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-06-01&end=2025-06-01")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('totals.2025-06-01', 100));
});

it('posts a correction to the ledger when a cell is typed', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);

    makeEntry($wallet, ['date' => '2025-06-02', 'type' => 'in', 'amount' => 1600]);

    // The 3rd closes at 1,700 on the entries alone; typing 1,500 is a 200 debit.
    $this->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/balance",
        ['date' => '2025-06-03', 'balance' => '1500'],
    )->assertRedirect();

    $correction = Transaction::where('account_id', $wallet->id)
        ->where('is_balance_adjustment', true)
        ->sole();

    expect($correction->date)->toBe('2025-06-03')
        ->and($correction->type)->toBe('out')
        ->and((float) $correction->amount)->toBe(200.0)
        ->and($correction->transactionType->name)->toBe('Balance Adjustment');
});

it('re-flows the days after a typed cell', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance";

    makeEntry($wallet, ['date' => '2025-06-02', 'type' => 'in', 'amount' => 1600]);

    $this->put("{$url}/{$wallet->id}/balance", ['date' => '2025-06-03', 'balance' => '1500'])
        ->assertRedirect();

    $this->get("{$url}?start=2025-06-02&end=2025-06-04")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Untouched before the correction, and carried forward after it.
            ->where('rows.0.days.2025-06-02', 1700)
            ->where('rows.0.days.2025-06-03', 1500)
            ->where('rows.0.days.2025-06-04', 1500));
});

it('rewrites a day\'s own correction rather than stacking a second', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/balance";

    $this->put($url, ['date' => '2025-06-03', 'balance' => '500'])->assertRedirect();
    $this->put($url, ['date' => '2025-06-03', 'balance' => '900'])->assertRedirect();

    $corrections = Transaction::where('account_id', $wallet->id)
        ->where('is_balance_adjustment', true)
        ->get();

    expect($corrections)->toHaveCount(1)
        ->and((float) $corrections->first()->amount)->toBe(800.0)
        ->and($corrections->first()->type)->toBe('in');
});

it('clears a cell by dropping its correction', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance";

    $this->put("{$url}/{$wallet->id}/balance", ['date' => '2025-06-03', 'balance' => '500'])
        ->assertRedirect();
    $this->put("{$url}/{$wallet->id}/balance", ['date' => '2025-06-03', 'balance' => null])
        ->assertRedirect();

    expect(Transaction::where('account_id', $wallet->id)->count())->toBe(0);

    $this->get("{$url}?start=2025-06-03&end=2025-06-03")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.0.days.2025-06-03', 100));
});

it('writes no correction when the day already closes at the figure typed', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);

    $this->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/balance",
        ['date' => '2025-06-03', 'balance' => '100'],
    )->assertRedirect();

    expect(Transaction::where('account_id', $wallet->id)->count())->toBe(0);
});

it('leaves the correction last on its day so the day closes on it', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-05-01', ['opening_balance' => 100]);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}";

    $this->post("{$url}/entries", entryPayload($workspace, [
        'date' => '2025-06-03', 'type' => 'in', 'amount' => '400',
    ]))->assertRedirect();

    $this->put("{$url}/balance", ['date' => '2025-06-03', 'balance' => '1000'])
        ->assertRedirect();

    $ledger = Transaction::where('account_id', $wallet->id)
        ->orderBy('position')
        ->get();

    expect($ledger)->toHaveCount(2)
        ->and($ledger->last()->is_balance_adjustment)->toBeTrue()
        // 100 opening + 400 entry + 500 correction — the correction closes it.
        ->and((float) $ledger->last()->running_balance)->toBe(1000.0);
});

it('backfills a day before the wallet was created, pulling the row back to it', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-06-03', ['opening_balance' => 100]);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance";

    $this->put("{$url}/{$wallet->id}/balance", ['date' => '2025-06-01', 'balance' => '500'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->get("{$url}?start=2025-05-31&end=2025-06-03")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // The row now starts at the back-dated correction, not at the
            // day the wallet happened to be created.
            ->where('rows.0.opened_on', '2025-06-01')
            ->where('rows.0.days.2025-05-31', null)
            ->where('rows.0.days.2025-06-01', 500)
            ->where('rows.0.days.2025-06-02', 500)
            ->where('rows.0.days.2025-06-03', 500));
});

it('starts a row at a back-dated entry rather than at the wallet\'s creation', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWalletOpenedOn($workspace, '2025-06-03', ['opening_balance' => 100]);

    makeEntry($wallet, ['date' => '2025-06-01', 'type' => 'in', 'amount' => 400]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance?start=2025-05-31&end=2025-06-02")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.opened_on', '2025-06-01')
            ->where('rows.0.days.2025-05-31', null)
            ->where('rows.0.days.2025-06-01', 500)
            ->where('rows.0.days.2025-06-02', 500));
});

it('refuses a future day, whose ledger there is nothing to correct', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);

    $this->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/balance",
        ['date' => now()->addDay()->toDateString(), 'balance' => '100'],
    )->assertSessionHasErrors('date');

    expect(Transaction::where('account_id', $wallet->id)->count())->toBe(0);
});

it('refuses to correct a company account or another workspace\'s wallet', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance";
    $yesterday = now()->subDay()->toDateString();

    $company = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Company BPI',
        'opening_balance' => 0,
        'currency' => 'PHP',
    ]);

    ['workspace' => $other] = makeWorkspaceWithOwner();
    $foreign = makeWallet($other);

    $this->put("{$url}/{$company->id}/balance", ['date' => $yesterday, 'balance' => '100'])
        ->assertNotFound();
    $this->put("{$url}/{$foreign->id}/balance", ['date' => $yesterday, 'balance' => '100'])
        ->assertNotFound();

    expect(Transaction::count())->toBe(0);
});

it('does not let a viewer without the manage grant type a cell', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewGoTymeBalance->value],
        'Sales & Marketing',
    );

    $this->actingAs($viewer)->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/balance",
        ['date' => now()->subDay()->toDateString(), 'balance' => '100'],
    )->assertForbidden();

    expect(Transaction::count())->toBe(0);
});

it('edits a wallet', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['name' => 'Juan', 'opening_balance' => 100]);

    $this->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}",
        walletPayload([
            'name' => 'Juana',
            'wallet_type' => 'backup',
            'opening_balance' => '250.75',
            'is_active' => false,
        ]),
    )->assertRedirect("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance");

    $wallet->refresh();

    expect($wallet->name)->toBe('Juana')
        ->and($wallet->wallet_type)->toBe(WalletType::Backup)
        ->and((float) $wallet->opening_balance)->toBe(250.75)
        ->and($wallet->is_active)->toBeFalse()
        // Still a wallet: an edit cannot promote one into a company account.
        ->and($wallet->is_user_wallet)->toBeTrue();
});

it('re-flows the ledger when the opening balance is edited', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['opening_balance' => 100]);

    $entry = makeEntry($wallet, ['date' => '2025-06-02', 'type' => 'in', 'amount' => 500]);
    $wallet->resequenceRunningBalances();

    expect((float) $entry->refresh()->running_balance)->toBe(600.0);

    $this->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}",
        walletPayload(['opening_balance' => '300']),
    )->assertRedirect();

    expect((float) $entry->refresh()->running_balance)->toBe(800.0);
});

it('refuses to edit a company account through the wallet endpoint', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $company = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Company BPI',
        'opening_balance' => 0,
        'currency' => 'PHP',
    ]);

    $this->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$company->id}",
        walletPayload(),
    )->assertNotFound();

    expect($company->refresh()->name)->toBe('Company BPI');
});

it('refuses to edit another workspace\'s wallet', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    ['workspace' => $other] = makeWorkspaceWithOwner();
    $foreign = makeWallet($other, ['name' => 'Theirs']);

    $this->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$foreign->id}",
        walletPayload(),
    )->assertNotFound();

    expect($foreign->refresh()->name)->toBe('Theirs');
});

it('deletes a wallet and the liquidation entries behind it', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);
    makeEntry($wallet);
    makeEntry($wallet, ['date' => '2025-06-05']);

    $this->delete("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertRedirect("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance");

    expect(Account::find($wallet->id))->toBeNull()
        ->and(Transaction::where('account_id', $wallet->id)->count())->toBe(0);
});

it('hands the grid the entry count a delete would take with it', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);
    makeEntry($wallet);
    makeEntry($wallet, ['date' => '2025-06-05']);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.0.entry_count', 2));
});

it('refuses to delete a company account through the wallet endpoint', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $company = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Company BPI',
        'opening_balance' => 0,
        'currency' => 'PHP',
    ]);

    $this->delete("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$company->id}")
        ->assertNotFound();

    expect(Account::find($company->id))->not->toBeNull();
});

it('does not let a viewer without the manage grant edit or delete a wallet', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['name' => 'Juan']);

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewGoTymeBalance->value],
        'Sales & Marketing',
    );

    $this->actingAs($viewer)->put(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}",
        walletPayload(),
    )->assertForbidden();

    $this->actingAs($viewer)
        ->delete("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertForbidden();

    expect($wallet->refresh()->name)->toBe('Juan');
});

it('writes a running balance of exactly zero rather than leaving it null', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['opening_balance' => 1000]);

    // Spends the wallet down to nothing: the figure is 0, which a null column
    // casts to as well — so the row has to be written, not skipped as unmoved.
    $entry = makeEntry($wallet, ['type' => 'out', 'amount' => 1000]);

    $wallet->resequenceRunningBalances();

    expect($entry->refresh()->running_balance)->not->toBeNull()
        ->and((float) $entry->running_balance)->toBe(0.0);
});

it('orders main above backup for the same holder', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    makeWallet($workspace, ['name' => 'Lowii Alipala', 'wallet_type' => WalletType::Backup]);
    makeWallet($workspace, ['name' => 'Lowii Alipala', 'wallet_type' => WalletType::Main]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.wallet_type', 'main')
            ->where('rows.1.wallet_type', 'backup'));
});

it('opens a wallet detail page with its liquidation ledger', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['name' => 'Tristan Harold Fatalla']);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}";

    foreach ([
        ['date' => '2025-06-04', 'description' => 'Funds from ate MY', 'type' => 'in', 'amount' => '15000'],
        ['date' => '2025-06-04', 'description' => 'FB ADS', 'type' => 'out', 'amount' => '3114.67'],
        ['date' => '2025-06-07', 'description' => 'Funds from ate MY', 'type' => 'in', 'amount' => '6900'],
    ] as $entry) {
        $this->post("{$url}/entries", entryPayload($workspace, $entry))->assertRedirect();
    }

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/sales-marketing/go-tyme-balance/show')
            ->where('account.name', 'Tristan Harold Fatalla')
            ->has('entries', 3)
            // Oldest first, with the balance running through them.
            ->where('entries.0.running_balance', 15000)
            ->where('entries.1.running_balance', 11885.33)
            ->where('entries.2.running_balance', 18785.33));
});

it('runs the ledger on from the wallet\'s opening balance', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['opening_balance' => 100]);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}";

    $this->post("{$url}/entries", entryPayload($workspace, ['type' => 'in', 'amount' => '50']));
    $this->post("{$url}/entries", entryPayload($workspace, ['type' => 'out', 'amount' => '30']));

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // 100 + 50, then − 30. Not 50 and 20.
            ->where('entries.0.running_balance', 150)
            ->where('entries.1.running_balance', 120)
            ->where('totals.actual_balance', 120));
});

it('shows a wallet with an opening balance and no entries at that balance', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['opening_balance' => 100]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('totals.actual_balance', 100));
});

it('carries the opening balance into the grid\'s today column', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace, ['opening_balance' => 100]);
    $today = now()->toDateString();

    $this->post(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries",
        entryPayload($workspace, ['type' => 'in', 'amount' => '50']),
    )->assertRedirect();

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where("rows.0.days.{$today}", 150));
});

it('stores liquidation entries as finance transactions, not a separate table', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);
    $type = makeLedgerType($workspace, 'Funds');

    $this->post("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries", [
        'date' => '2025-06-04',
        'transaction_type_id' => $type->id,
        'description' => 'Funds from ate MY',
        'department' => 'Marketing',
        'type' => 'in',
        'amount' => '15000',
        'notes' => 'Additional Funds',
    ])->assertRedirect();

    $entry = Transaction::where('account_id', $wallet->id)->sole();

    // Every field lands in a column finance_transactions already had.
    expect($entry->transaction_type_id)->toBe($type->id)
        ->and($entry->description)->toBe('Funds from ate MY')
        ->and($entry->department)->toBe('Marketing')
        ->and($entry->type)->toBe('in')
        ->and((float) $entry->amount)->toBe(15000.0)
        ->and($entry->notes)->toBe('Additional Funds')
        ->and((float) $entry->running_balance)->toBe(15000.0)
        ->and($entry->workspace_id)->toBe($workspace->id);
});

it('renders the transaction type as the ledger\'s Transaction column', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);
    $type = makeLedgerType($workspace, 'Pondo');

    $this->post(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries",
        entryPayload($workspace, ['transaction_type_id' => $type->id, 'description' => 'Pondo top-up']),
    )->assertRedirect();

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entries.0.transaction_type_name', 'Pondo')
            ->where('entries.0.description', 'Pondo top-up'));
});

it('offers the workspace\'s transaction types to the entry form', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);

    makeLedgerType($workspace, 'Funds');
    makeLedgerType($workspace, 'Ad Spent');

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transactionTypes', 2)
            ->where('transactionTypes.0.name', 'Ad Spent')
            ->where('transactionTypes.1.name', 'Funds'));
});

it('refuses a transaction type from another workspace', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);

    ['workspace' => $other] = makeWorkspaceWithOwner();
    $foreignType = makeLedgerType($other, 'Sneaky');

    $this->post(
        "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries",
        entryPayload($workspace, ['transaction_type_id' => $foreignType->id]),
    )->assertSessionHasErrors('transaction_type_id');

    expect(Transaction::count())->toBe(0);
});

it('hands the page its rows, its option lists and a separate totals aggregate', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}";

    foreach ([
        ['date' => '2025-06-04', 'type' => 'in', 'amount' => '15000'],
        ['date' => '2025-06-07', 'type' => 'in', 'amount' => '6900'],
        ['date' => '2025-06-12', 'type' => 'out', 'amount' => '3114.67'],
    ] as $entry) {
        $this->post("{$url}/entries", entryPayload($workspace, $entry));
    }

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries', 3)
            ->has('transactionTypes')
            ->has('departments')
            // Rows arrive in ledger order with their balances already stored;
            // sorting, filtering, the range and paging are the page's job.
            ->where('entries.0.running_balance', 15000)
            ->where('entries.1.running_balance', 21900)
            ->where('entries.2.running_balance', 18785.33)
            // Totals come off their own aggregate on this same response.
            ->where('totals.total_credit', 21900)
            ->where('totals.credit_count', 2)
            ->where('totals.total_debit', 3114.67)
            ->where('totals.debit_count', 1)
            ->where('totals.actual_balance', 18785.33)
            ->where('totals.entry_count', 3)
            ->where('totals.first_date', '2025-06-04')
            ->where('totals.last_date', '2025-06-12'));
});

it('zeroes the totals for a wallet with no entries', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries', 0)
            ->where('totals.total_credit', 0)
            ->where('totals.total_debit', 0)
            ->where('totals.actual_balance', 0)
            ->where('totals.entry_count', 0)
            ->where('totals.first_date', null)
            ->where('totals.last_date', null));
});

it('counts only this wallet in its totals', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $wallet = makeWallet($workspace, ['name' => 'Wallet A']);
    $other = makeWallet($workspace, ['name' => 'Wallet B']);

    makeEntry($wallet, ['type' => 'out', 'amount' => 100]);
    makeEntry($other, ['type' => 'out', 'amount' => 999]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.total_debit', 100)
            ->where('totals.entry_count', 1));
});

it('re-flows every running balance when an entry is back-dated in', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}";

    $this->post("{$url}/entries", entryPayload($workspace, ['date' => '2025-06-10', 'type' => 'in', 'amount' => '100']));
    // Added second, dated earlier — it must sort to the front and push the
    // other row's stored balance up.
    $this->post("{$url}/entries", entryPayload($workspace, ['date' => '2025-06-01', 'type' => 'in', 'amount' => '50']));

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entries.0.date', '2025-06-01')
            ->where('entries.0.running_balance', 50)
            ->where('entries.1.running_balance', 150));

    // The stored column, not just the rendered one, has been rewritten.
    expect((float) Transaction::where('date', '2025-06-10')->sole()->running_balance)->toBe(150.0);
});

it('rejects a non-positive amount and an unknown type', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);
    $url = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries";

    $this->post($url, entryPayload($workspace, ['amount' => '0']))->assertSessionHasErrors('amount');
    $this->post($url, entryPayload($workspace, ['type' => 'sideways']))->assertSessionHasErrors('type');

    expect(Transaction::count())->toBe(0);
});

it('edits and deletes a liquidation entry, re-flowing the balances', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);
    $base = "/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries";

    $this->post($base, entryPayload($workspace, ['type' => 'in', 'amount' => '100']));
    $entry = Transaction::sole();

    $this->put("{$base}/{$entry->id}", entryPayload($workspace, [
        'type' => 'in',
        'amount' => '250.50',
        'description' => 'Pondo',
    ]))->assertRedirect();

    expect((float) $entry->fresh()->amount)->toBe(250.50)
        ->and($entry->fresh()->description)->toBe('Pondo')
        ->and((float) $entry->fresh()->running_balance)->toBe(250.50);

    $this->delete("{$base}/{$entry->id}")->assertRedirect();

    expect(Transaction::count())->toBe(0);
});

it('refuses to touch an entry belonging to another wallet', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $wallet = makeWallet($workspace, ['name' => 'Wallet A']);
    $other = makeWallet($workspace, ['name' => 'Wallet B']);
    $entry = makeEntry($other);

    $this->delete("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries/{$entry->id}")
        ->assertNotFound();

    expect(Transaction::count())->toBe(1);
});

it('does not let a viewer without the manage grant add an entry', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewGoTymeBalance->value],
        'Sales & Marketing',
    );

    $this->actingAs($viewer)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}/entries", entryPayload($workspace))
        ->assertForbidden();

    expect(Transaction::count())->toBe(0);
});

it('keeps a wallet\'s liquidation entries out of the finance transactions list', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $wallet = makeWallet($workspace);
    makeEntry($wallet);

    $company = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Company BPI',
        'opening_balance' => 0,
        'currency' => 'PHP',
    ]);
    makeEntry($company, ['description' => 'Company spend']);

    $this->get("/workspaces/{$workspace->slug}/finance/transactions")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'Company spend'));
});

it('offers the workspace\'s active departments to the entry form', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);

    $workspace->departments()->createMany([
        ['name' => 'Marketing', 'is_active' => true],
        ['name' => 'Operations', 'is_active' => true],
        ['name' => 'Retired Unit', 'is_active' => false],
    ]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Active only, alphabetical — the same list the Finance form uses.
            ->where('departments', ['Marketing', 'Operations']));
});

it('404s the detail page for a company account', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    $company = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Company BPI',
        'opening_balance' => 0,
        'currency' => 'PHP',
    ]);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$company->id}")
        ->assertNotFound();
});

it('404s the detail page for another workspace\'s wallet', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableSalesMarketing($workspace);

    ['workspace' => $other] = makeWorkspaceWithOwner();
    $foreign = makeWallet($other);

    $this->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$foreign->id}")
        ->assertNotFound();
});

it('lets a viewer without the manage grant open the detail page', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    enableSalesMarketing($workspace);
    $wallet = makeWallet($workspace);

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewGoTymeBalance->value],
        'Sales & Marketing',
    );

    $this->actingAs($viewer)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/go-tyme-balance/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));
});
