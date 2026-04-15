<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\TransactionRequest;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Remittance;
use Modules\Finance\Models\Transaction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TransactionController extends Controller
{
    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    protected function ensureOwns(Workspace $workspace, Transaction $transaction): void
    {
        if ($transaction->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    protected function validateWorkspaceFor(Workspace $workspace, array $data): void
    {
        $accountOk = Account::where('id', $data['account_id'] ?? null)
            ->where('workspace_id', $workspace->id)->exists();
        if (! $accountOk) {
            throw ValidationException::withMessages(['account_id' => 'Invalid account for this workspace.']);
        }

        if (! empty($data['remittance_id'])) {
            $remittanceOk = Remittance::where('id', $data['remittance_id'])
                ->where('workspace_id', $workspace->id)->exists();
            if (! $remittanceOk) {
                throw ValidationException::withMessages(['remittance_id' => 'Invalid remittance for this workspace.']);
            }
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        $transactions = QueryBuilder::for(
            Transaction::where('workspace_id', $workspace->id)->with(['account', 'remittance'])
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where('description', 'like', "%{$v}%")),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::exact('category'),
            ])
            ->allowedSorts(['id', 'date', 'amount', 'type', 'transaction_type', 'category', 'created_at'])
            ->defaultSort('-date', '-created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('workspaces/finance/transactions/index', [
            'workspace' => $workspace,
            'transactions' => $transactions,
            'accounts' => Account::where('workspace_id', $workspace->id)
                ->orderBy('name')->get(['id', 'name', 'currency']),
            'remittances' => Remittance::where('workspace_id', $workspace->id)
                ->doesntHave('transaction')
                ->orderByDesc('date')
                ->get(['id', 'courier', 'reference_no', 'date', 'net_amount']),
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(TransactionRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->validateWorkspaceFor($workspace, $request->validated());

        Transaction::create([...$request->validated(), 'workspace_id' => $workspace->id]);

        return redirect()->back()->with('success', 'Transaction created.');
    }

    public function update(TransactionRequest $request, Workspace $workspace, Transaction $transaction)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $transaction);
        $this->validateWorkspaceFor($workspace, $request->validated());

        $transaction->update($request->validated());

        return redirect()->back()->with('success', 'Transaction updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Transaction $transaction)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $transaction);

        $transaction->delete();

        return redirect()->back()->with('success', 'Transaction deleted.');
    }
}
