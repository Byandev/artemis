<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\AccountRequest;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountController extends Controller
{
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

        $accounts = QueryBuilder::for(Account::where('workspace_id', $workspace->id)->withCount(['transactions']))
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where('name', 'like', "%{$v}%")),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['id', 'name', 'currency', 'is_active', 'created_at'])
            ->defaultSort('name')
            ->paginate(15)
            ->withQueryString();

        // Get last transaction per account for running_balance
        $accountIds = collect($accounts->items())->pluck('id');
        $lastBalances = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('account_id', $accountIds)
            ->whereIn('id', function ($q) use ($workspace, $accountIds) {
                $q->selectRaw('(SELECT t2.id FROM finance_transactions t2 WHERE t2.account_id = finance_transactions.account_id AND t2.workspace_id = ? ORDER BY t2.date DESC, t2.id DESC LIMIT 1)', [$workspace->id])
                    ->from('finance_transactions')
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('account_id', $accountIds)
                    ->groupBy('account_id');
            })
            ->pluck('running_balance', 'account_id');

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
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(AccountRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        Account::create([...$request->validated(), 'workspace_id' => $workspace->id]);

        return redirect()->route('workspaces.finance.accounts.index', $workspace->slug)
            ->with('success', 'Account created.');
    }

    public function show(Request $request, Workspace $workspace, Account $account)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $account);

        $transactions = $account->transactions()
            ->with('remittance:id,transaction_id,courier,soa_number')
            ->orderByDesc('date')
            ->orderByDesc('running_balance')
            ->get();

        return Inertia::render('workspaces/finance/accounts/show', [
            'workspace' => $workspace,
            'account' => $account,
            'transactions' => $transactions,
        ]);
    }

    public function update(AccountRequest $request, Workspace $workspace, Account $account)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $account);

        $account->update($request->validated());

        return redirect()->route('workspaces.finance.accounts.index', $workspace->slug)
            ->with('success', 'Account updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Account $account)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $account);

        $account->delete();

        return redirect()->route('workspaces.finance.accounts.index', $workspace->slug)
            ->with('success', 'Account deleted.');
    }
}
