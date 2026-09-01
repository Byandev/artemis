<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\AccountRequest;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\TransactionType;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountController extends Controller
{
    use AuthorizesRequests;

    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    protected function ensureOwns(Workspace $workspace, Account $account): void
    {
        if ($account->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceAccounts->value, $workspace);

        // User wallets (created on the S&M Go Tyme Balance page) share this
        // table but are not company accounts, so they stay out of this listing.
        $accounts = QueryBuilder::for(
            Account::where('workspace_id', $workspace->id)->excludingWallets()->withCount(['transactions'])
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where('name', 'like', "%{$v}%")),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['id', 'name', 'currency', 'is_active', 'created_at'])
            ->defaultSort('name')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get last transaction per account for running_balance
        $lastBalances = Account::currentBalances(
            $workspace->id,
            collect($accounts->items())->pluck('id'),
        );

        // Append current_balance to each account
        $accounts->through(function ($account) use ($lastBalances) {
            $account->current_balance = $lastBalances->has($account->id)
                ? (float) $lastBalances->get($account->id)
                : (float) $account->opening_balance;

            return $account;
        });

        return Inertia::render('workspaces/finance/accounts/index', [
            'workspace' => $workspace,
            'accounts' => $accounts,
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(AccountRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceAccounts->value, $workspace);

        Account::create([...$request->validated(), 'workspace_id' => $workspace->id]);

        return redirect()->route('workspaces.finance.accounts.index', $workspace->slug)
            ->with('success', 'Account created.');
    }

    public function show(Request $request, Workspace $workspace, Account $account)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceAccounts->value, $workspace);
        $this->ensureOwns($workspace, $account);

        $transactions = $account->transactions()
            ->with('remittance:id,transaction_id,courier,soa_number')
            ->orderByDesc('date')
            ->orderByDesc('position')
            ->get();

        return Inertia::render('workspaces/finance/accounts/show', [
            'workspace' => $workspace,
            'account' => $account,
            'transactions' => $transactions,
            'transactionTypes' => TransactionType::where('workspace_id', $workspace->id)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(AccountRequest $request, Workspace $workspace, Account $account)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceAccounts->value, $workspace);
        $this->ensureOwns($workspace, $account);

        $account->update($request->validated());

        return redirect()->route('workspaces.finance.accounts.index', $workspace->slug)
            ->with('success', 'Account updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Account $account)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::DeleteFinanceAccounts->value, $workspace);
        $this->ensureOwns($workspace, $account);

        $account->delete();

        return redirect()->route('workspaces.finance.accounts.index', $workspace->slug)
            ->with('success', 'Account deleted.');
    }
}
