<?php

namespace App\Http\Controllers\Workspaces\SalesMarketing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Enums\WalletType;
use Modules\Finance\Models\Account;

/**
 * Go Tyme Balance — a sibling page under Sales & Marketing. It rides the S&M
 * module flag like the rest of the group and carries its own grants.
 *
 * The accounts it creates are ordinary Finance accounts (same table, same
 * balances, same transactions), flagged `is_user_wallet` so they read as
 * per-user wallets and stay out of the Finance → Accounts listing.
 */
class GoTymeBalanceController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorizeAccess($workspace);

        $accounts = Account::where('workspace_id', $workspace->id)
            ->wallets()
            ->orderBy('name')
            ->get(['id', 'name', 'opening_balance', 'currency', 'notes', 'is_active', 'is_user_wallet', 'wallet_type']);

        $balances = Account::currentBalances($workspace->id, $accounts->pluck('id'));

        return Inertia::render('workspaces/sales-marketing/go-tyme-balance/index', [
            'workspace' => $workspace,
            'accounts' => $accounts->map(fn (Account $account) => [
                ...$account->only(['id', 'name', 'currency', 'notes', 'is_active', 'is_user_wallet']),
                'wallet_type' => $account->wallet_type?->value,
                'opening_balance' => (float) $account->opening_balance,
                'current_balance' => (float) $balances->get($account->id, $account->opening_balance),
            ]),
            'canManage' => $request->user()->hasPermission(
                Permission::ManageGoTymeBalance->value,
                $workspace,
            ),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorizeAccess($workspace);

        $this->authorize(Permission::ManageGoTymeBalance->value, $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'wallet_type' => ['required', Rule::enum(WalletType::class)],
            'opening_balance' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Account::create([
            ...$validated,
            'currency' => strtoupper($validated['currency']),
            'is_active' => $request->boolean('is_active', true),
            'workspace_id' => $workspace->id,
            // What makes this a wallet rather than a company account: set here
            // and nowhere else, so only this page can mint one.
            'is_user_wallet' => true,
        ]);

        return redirect()
            ->route('workspaces.sales-marketing.go-tyme-balance', $workspace)
            ->with('success', 'Account created.');
    }

    private function authorizeAccess(Workspace $workspace): void
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewGoTymeBalance->value, $workspace);
    }
}
