<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\TransactionRequest;
use Modules\Finance\Models\Account;
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
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        $transactions = QueryBuilder::for(
            Transaction::where('workspace_id', $workspace->id)
                ->with(['account', 'remittance'])
        )

            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where('description', 'like', "%{$v}%")),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::exact('sub_category'),
                AllowedFilter::callback('missing_type', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN) ? $q->whereNull('transaction_type') : $q),
                AllowedFilter::callback('expenses_missing_sub', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN)
                    ? $q->where('transaction_type', 'expenses')->whereNull('sub_category')
                    : $q),
            ])
            ->allowedSorts(['id', 'date', 'amount', 'type', 'transaction_type', 'sub_category', 'created_at'])
            ->defaultSort('-id')
            ->paginate((int) $request->input('per_page', 100))
            ->withQueryString();

        return Inertia::render('workspaces/finance/transactions/index', [
            'workspace' => $workspace,
            'transactions' => $transactions,
            'accounts' => Account::where('workspace_id', $workspace->id)
                ->orderBy('name')->get(['id', 'name', 'currency']),
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

    public function import(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.account_id' => ['required', 'integer'],
            'rows.*.date' => ['required', 'date'],
            'rows.*.description' => ['required', 'string', 'max:255'],
            'rows.*.type' => ['required', 'in:in,out'],
            'rows.*.transaction_type' => ['nullable', 'in:funds,profit_share,expenses,transfer,remittance,loan,loan_payment,refund,voided,courier_damaged_settlement'],
            'rows.*.amount' => ['required', 'numeric', 'min:0'],
            'rows.*.running_balance' => ['nullable', 'numeric'],
            'rows.*.sub_category' => ['nullable', 'in:ad_spent,cogs,subscription,shipping_fee,operation_expense,salary,transfer_fee,seminar_fee,rent,others'],
            'rows.*.notes' => ['nullable', 'string'],
        ]);

        $accountIds = collect($validated['rows'])->pluck('account_id')->unique();
        $validAccountIds = Account::where('workspace_id', $workspace->id)
            ->whereIn('id', $accountIds)->pluck('id')->all();

        if (count($validAccountIds) !== $accountIds->count()) {
            return redirect()->back()->withErrors(['rows' => 'One or more accounts do not belong to this workspace.']);
        }

        $now = now();
        $records = collect($validated['rows'])->map(fn ($r) => [
            ...$r,
            'workspace_id' => $workspace->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        Transaction::insert($records);

        return redirect()->back()->with('success', count($records).' transactions imported.');
    }

    public function bulkUpdateType(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'transaction_type' => ['nullable', 'in:funds,profit_share,expenses,transfer,remittance,loan,loan_payment,refund,voided,courier_damaged_settlement'],
        ]);

        $updated = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['ids'])
            ->update(['transaction_type' => $validated['transaction_type'] ?? null]);

        return redirect()->back()->with('success', "{$updated} transactions updated.");
    }

    public function bulkUpdateSubCategory(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'sub_category' => ['nullable', 'in:ad_spent,cogs,subscription,shipping_fee,operation_expense,salary,transfer_fee,seminar_fee,rent,others'],
        ]);

        $updated = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['ids'])
            ->update(['sub_category' => $validated['sub_category'] ?? null]);

        return redirect()->back()->with('success', "{$updated} transactions updated.");
    }

    public function destroy(Request $request, Workspace $workspace, Transaction $transaction)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $transaction);

        $transaction->delete();

        return redirect()->back()->with('success', 'Transaction deleted.');
    }
}
