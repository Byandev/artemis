<?php

use App\Enums\Permission;
use App\Models\User;
use Modules\Finance\Enums\WalletType;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

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
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Wallet — Juan')
            ->where('accounts.0.is_user_wallet', true)
            ->where('accounts.0.wallet_type', 'backup')
            ->where('accounts.0.current_balance', 250));
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
