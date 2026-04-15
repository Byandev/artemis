<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\AccountRequest;
use Modules\Finance\Models\Account;
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

        $accounts = QueryBuilder::for(Account::where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where('name', 'like', "%{$v}%")),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['id', 'name', 'currency', 'is_active', 'created_at'])
            ->defaultSort('name')
            ->paginate(15)
            ->withQueryString();

        $accounts->through(function (Account $a) {
            $a->current_balance = (float) $a->current_balance;
            $a->total_in = (float) $a->total_in;
            $a->total_out = (float) $a->total_out;

            return $a;
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
            ->with('remittance')
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();

        $running = (float) $account->opening_balance;
        $rows = $transactions->map(function ($t) use (&$running) {
            $running += $t->type === 'in' ? (float) $t->amount : -(float) $t->amount;

            return [
                'id' => $t->id,
                'date' => $t->date->toDateString(),
                'description' => $t->description,
                'type' => $t->type,
                'transaction_type' => $t->transaction_type,
                'amount' => (float) $t->amount,
                'category' => $t->category,
                'remittance' => $t->remittance ? [
                    'id' => $t->remittance->id,
                    'courier' => $t->remittance->courier,
                    'reference_no' => $t->remittance->reference_no,
                ] : null,
                'notes' => $t->notes,
                'running_balance' => round($running, 2),
            ];
        });

        return Inertia::render('workspaces/finance/accounts/show', [
            'workspace' => $workspace,
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'opening_balance' => (float) $account->opening_balance,
                'current_balance' => (float) $account->current_balance,
                'notes' => $account->notes,
                'is_active' => $account->is_active,
            ],
            'rows' => $rows,
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
