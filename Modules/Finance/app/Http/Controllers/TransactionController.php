<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Logging\LogCategory;
use App\Enums\Permission;
use App\Facades\Activity;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\TransactionRequest;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TransactionController extends Controller
{
    use AuthorizesRequests;

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

    protected function buildQuery(Workspace $workspace): QueryBuilder
    {
        return QueryBuilder::for(Transaction::where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($q2) use ($v) {
                    $q2->where('description', 'like', "%{$v}%")
                        ->orWhere('running_balance', $v)
                        ->orWhere('amount', $v);
                })),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::callback('transaction_type', fn ($q, $v) => is_array($v) ? $q->whereIn('transaction_type', $v) : $q->where('transaction_type', $v)),
                AllowedFilter::callback('transaction_type_id', fn ($q, $v) => is_array($v) ? $q->whereIn('transaction_type_id', $v) : $q->where('transaction_type_id', $v)),
                AllowedFilter::callback('sub_category', fn ($q, $v) => is_array($v) ? $q->whereIn('sub_category', $v) : $q->where('sub_category', $v)),
                AllowedFilter::callback('missing_type', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN) ? $q->whereNull('transaction_type_id') : $q),
                AllowedFilter::callback('expenses_missing_sub', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN)
                    ? $q->where('transaction_type_id', $this->expensesTypeId($workspace))->whereNull('sub_category')
                    : $q),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate('date', '>=', $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate('date', '<=', $v)),
            ]);
    }

    /**
     * Resolve the id of the workspace's "expenses" transaction type (the legacy
     * name), used by the "expenses without sub category" filter now that types
     * are referenced by id. Returns null (matches nothing) if absent.
     */
    protected function expensesTypeId(Workspace $workspace): ?int
    {
        return TransactionType::where('workspace_id', $workspace->id)
            ->where('name', 'expenses')
            ->value('id');
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceTransactions->value, $workspace);

        $transactions = $this->buildQuery($workspace)
            ->with(['account', 'remittance', 'requester:id,name', 'approver:id,name', 'chargeToUser:id,name'])
            ->orderBy('date', 'desc')
            ->orderBy('position', 'desc')
            ->paginate((int) $request->input('per_page', 100))
            ->withQueryString();

        $totals = $this->buildQuery($workspace)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_credit")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_debit")
            ->first();

        return Inertia::render('workspaces/finance/transactions/index', [
            'workspace' => $workspace,
            'transactions' => $transactions,
            'accounts' => Account::where('workspace_id', $workspace->id)
                ->orderBy('name')->get(['id', 'name', 'currency']),
            'transactionTypes' => TransactionType::where('workspace_id', $workspace->id)
                ->orderBy('name')->get(['id', 'name']),
            'users' => $workspace->users()->get(['users.id', 'users.name']),
            'totals' => [
                'credit' => (float) $totals->total_credit,
                'debit' => (float) $totals->total_debit,
            ],
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(TransactionRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceTransactions->value, $workspace);
        $this->validateWorkspaceFor($workspace, $request->validated());

        $data = $request->validated();

        // If position is provided (squeezing in), shift existing rows at that position and after
        if (! empty($data['position'])) {
            Transaction::where('workspace_id', $workspace->id)
                ->where('account_id', $data['account_id'])
                ->where('date', $data['date'])
                ->where('position', '>=', $data['position'])
                ->increment('position');
        } else {
            // Auto-assign: next position for this account+date
            $maxPos = Transaction::where('workspace_id', $workspace->id)
                ->where('account_id', $data['account_id'])
                ->where('date', $data['date'])
                ->max('position') ?? 0;

            $data['position'] = $maxPos + 1;
        }

        Transaction::create([...$data, 'workspace_id' => $workspace->id]);

        return redirect()->back()->with('success', 'Transaction created.');
    }

    public function update(TransactionRequest $request, Workspace $workspace, Transaction $transaction)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);
        $this->ensureOwns($workspace, $transaction);
        $this->validateWorkspaceFor($workspace, $request->validated());

        $data = $request->validated();

        // Preserve existing position if not provided
        if (empty($data['position'])) {
            unset($data['position']);
        }

        $transaction->update($data);

        return redirect()->back()->with('success', 'Transaction updated.');
    }

    public function import(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceTransactions->value, $workspace);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.account_id' => ['required', 'integer'],
            'rows.*.date' => ['required', 'date'],
            'rows.*.description' => ['required', 'string', 'max:255'],
            'rows.*.type' => ['required', 'in:in,out'],
            'rows.*.transaction_type' => ['nullable', 'in:funds,profit_share,expenses,transfer,remittance,loan,loan_payment,refund,voided,courier_damaged_settlement,capex,interest,interest_fee'],
            'rows.*.transaction_type_id' => ['nullable', Rule::exists('finance_transaction_types', 'id')->where('workspace_id', $workspace->id)],
            // requested_by / approved_by / charge_to are user references now, so
            // they are not accepted from free-text spreadsheet imports.
            'rows.*.department' => ['nullable', 'string', 'max:255'],
            'rows.*.reference_no' => ['nullable', 'string', 'max:255'],
            'rows.*.status' => ['nullable', Rule::in(['pending', 'approved', 'posted'])],
            'rows.*.amount' => ['required', 'numeric', 'min:0'],
            'rows.*.running_balance' => ['nullable', 'numeric'],
            'rows.*.position' => ['nullable', 'integer', 'min:1'],
            'rows.*.sub_category' => ['nullable', 'in:ad_spent,cogs,subscription,shipping_fee,delivery_fee,operation_expense,salary,transfer_fee,seminar_fee,rent,capex_payment,others'],
            'rows.*.notes' => ['nullable', 'string'],
        ]);

        $accountIds = collect($validated['rows'])->pluck('account_id')->unique();
        $validAccountIds = Account::where('workspace_id', $workspace->id)
            ->whereIn('id', $accountIds)->pluck('id')->all();

        if (count($validAccountIds) !== $accountIds->count()) {
            return redirect()->back()->withErrors(['rows' => 'One or more accounts do not belong to this workspace.']);
        }

        // Auto-assign positions per (account_id, date) group if not provided
        $positionCounters = [];
        $now = now();
        $records = collect($validated['rows'])->map(function ($r) use ($workspace, &$positionCounters, $now) {
            $key = $r['account_id'].'|'.$r['date'];
            if (! isset($positionCounters[$key])) {
                $positionCounters[$key] = Transaction::where('workspace_id', $workspace->id)
                    ->where('account_id', $r['account_id'])
                    ->where('date', $r['date'])
                    ->max('position') ?? 0;
            }

            if (empty($r['position'])) {
                $positionCounters[$key]++;
                $r['position'] = $positionCounters[$key];
            }

            // User-linked columns aren't part of the free-text import.
            unset($r['requested_by'], $r['approved_by'], $r['charge_to']);

            return [
                ...$r,
                'workspace_id' => $workspace->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        Transaction::insert($records);

        return redirect()->back()->with('success', count($records).' transactions imported.');
    }

    public function bulkUpdateType(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'transaction_type_id' => ['nullable', Rule::exists('finance_transaction_types', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $updated = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['ids'])
            ->update(['transaction_type_id' => $validated['transaction_type_id'] ?? null]);

        return redirect()->back()->with('success', "{$updated} transactions updated.");
    }

    public function bulkUpdateSubCategory(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'sub_category' => ['nullable', 'in:ad_spent,cogs,subscription,shipping_fee,delivery_fee,operation_expense,salary,transfer_fee,seminar_fee,rent,capex_payment,others'],
        ]);

        $updated = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['ids'])
            ->update(['sub_category' => $validated['sub_category'] ?? null]);

        return redirect()->back()->with('success', "{$updated} transactions updated.");
    }

    public function export(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        $transactions = QueryBuilder::for(
            Transaction::where('workspace_id', $workspace->id)
                ->with(['account', 'transactionType', 'requester:id,name', 'approver:id,name', 'chargeToUser:id,name'])
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($q2) use ($v) {
                    $q2->where('description', 'like', "%{$v}%")
                        ->orWhere('running_balance', $v)
                        ->orWhere('amount', $v);
                })),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::callback('transaction_type', fn ($q, $v) => is_array($v) ? $q->whereIn('transaction_type', $v) : $q->where('transaction_type', $v)),
                AllowedFilter::callback('transaction_type_id', fn ($q, $v) => is_array($v) ? $q->whereIn('transaction_type_id', $v) : $q->where('transaction_type_id', $v)),
                AllowedFilter::callback('sub_category', fn ($q, $v) => is_array($v) ? $q->whereIn('sub_category', $v) : $q->where('sub_category', $v)),
                AllowedFilter::callback('missing_type', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN) ? $q->whereNull('transaction_type_id') : $q),
                AllowedFilter::callback('expenses_missing_sub', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN)
                    ? $q->where('transaction_type_id', $this->expensesTypeId($workspace))->whereNull('sub_category')
                    : $q),
            ])
            ->orderBy('date', 'desc')
            ->orderBy('position', 'desc')
            ->get();

        Activity::build()
            ->asUser()
            ->workspace($workspace)
            ->category(LogCategory::Security)
            ->action('finance.transactions.exported')
            ->message("Exported {$transactions->count()} finance transaction(s)")
            ->metadata([
                'count' => $transactions->count(),
                'filters' => $request->only([
                    'search', 'account_id', 'type', 'transaction_type', 'transaction_type_id',
                    'sub_category', 'missing_type', 'expenses_missing_sub',
                ]),
            ])
            ->save();

        $fileName = 'transactions-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Posted Date', 'Accounts', 'Transaction', 'Requested By', 'Approved By',
                'Department', 'Type of Expense', 'Debit (Expense)', 'Credit (Income)',
                'Running Balance', 'Reference No.', 'Charge To', 'Status', 'Sub Category', 'Remarks',
            ]);

            foreach ($transactions as $txn) {
                fputcsv($out, [
                    $txn->date,
                    $txn->account?->name ?? '',
                    $txn->description,
                    $txn->requester?->name ?? '',
                    $txn->approver?->name ?? '',
                    $txn->department ?? '',
                    $txn->transactionType?->name ?? $txn->transaction_type ?? '',
                    $txn->type === 'out' ? $txn->amount : '',
                    $txn->type === 'in' ? $txn->amount : '',
                    $txn->running_balance ?? '',
                    $txn->reference_no ?? '',
                    $txn->chargeToUser?->name ?? '',
                    $txn->status ?? '',
                    $txn->sub_category ?? '',
                    $txn->notes ?? '',
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, Transaction $transaction)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::DeleteFinanceTransactions->value, $workspace);
        $this->ensureOwns($workspace, $transaction);

        $transaction->delete();

        return redirect()->back()->with('success', 'Transaction deleted.');
    }
}
