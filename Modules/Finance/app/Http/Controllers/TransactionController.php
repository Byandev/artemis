<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Logging\LogCategory;
use App\Enums\Permission;
use App\Facades\Activity;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\TransactionRequest;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FundRequest;
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
            ->with(['account', 'requester:id,name', 'approver:id,name', 'chargeToUsers:users.id,users.name', 'fundRequest:id,reference_no'])
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
            ...$this->formOptions($request, $workspace),
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

    public function create(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceTransactions->value, $workspace);

        return Inertia::render('workspaces/finance/transactions/create', [
            'workspace' => $workspace,
            ...$this->formOptions($request, $workspace),
            'defaultAccountId' => $request->integer('account_id') ?: null,
            'returnTo' => $this->safeReturnTo($request, $workspace),
        ]);
    }

    public function edit(Request $request, Workspace $workspace, Transaction $transaction)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);
        $this->ensureOwns($workspace, $transaction);

        return Inertia::render('workspaces/finance/transactions/edit', [
            'workspace' => $workspace,
            'transaction' => $transaction->load(['chargeToUsers:users.id,users.name', 'productShares', 'fundRequest:id,reference_no']),
            ...$this->formOptions($request, $workspace, $transaction),
            'returnTo' => $this->safeReturnTo($request, $workspace),
        ]);
    }

    /**
     * Shared option lists for the transaction form (account/type/user selects and
     * the product datalist).
     *
     * @param  Transaction|null  $editing  Left out of each account's current
     *                                     balance, so editing the newest entry
     *                                     builds on the one before it rather
     *                                     than on itself.
     */
    protected function formOptions(Request $request, Workspace $workspace, ?Transaction $editing = null): array
    {
        return [
            'accounts' => $this->accountOptions($workspace, $editing),
            'transactionTypes' => TransactionType::where('workspace_id', $workspace->id)
                ->orderBy('name')->get(['id', 'name']),
            'users' => $workspace->users()->get(['users.id', 'users.name']),
            // The product tags the picker offers (see productOptions).
            'products' => $this->productOptions($request, $workspace, $editing),
            // Approved requests a new entry can be filled in from.
            'fundRequests' => $this->fundRequestOptions($workspace),
            // The workspace's saved (active) departments, for the Department select.
            'departments' => Department::ofWorkspace($workspace)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
        ];
    }

    /**
     * The product tags the picker offers: the workspace's catalog products, the
     * same list a fund request is built from, so an entry filled in from one
     * lines up exactly. Any tag already saved on the entry being edited is
     * carried along too — the picker only renders a selection it has an option
     * for, so a tag from before this list (or from a since-renamed product)
     * would otherwise show up blank.
     *
     * @return list<string>
     */
    protected function productOptions(Request $request, Workspace $workspace, ?Transaction $editing = null): array
    {
        $products = Product::ofWorkspace($workspace)
            ->visibleTo($request->user(), $workspace)
            ->orderBy('name')
            ->pluck('name');

        $saved = $editing?->productShares->pluck('product')->all() ?? [];

        return $products->merge($saved)->filter()->unique()->values()->all();
    }

    /**
     * The fund requests a transaction can be filled in from — the workspace's
     * approved and released ones, newest first, each carrying the shares the
     * form copies across. Capped: this rides along with every form render, and
     * a request older than the most recent hundred is not what someone is
     * settling today.
     */
    protected function fundRequestOptions(Workspace $workspace): Collection
    {
        return FundRequest::where('workspace_id', $workspace->id)
            ->whereIn('status', FundRequest::APPROVED_STATUSES)
            ->with(['chargeToUsers:users.id,users.name', 'productShares', 'department:id,name'])
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (FundRequest $fundRequest) => [
                'id' => $fundRequest->id,
                'reference_no' => $fundRequest->reference_no,
                'request_date' => $fundRequest->request_date?->toDateString(),
                'amount_requested' => (float) $fundRequest->amount_requested,
                'status' => $fundRequest->status,
                // Copied onto the transaction when one is filled in from here.
                'transaction_type_id' => $fundRequest->transaction_type_id,
                'department' => $fundRequest->department?->name,
                'charge_to' => $fundRequest->chargeToUsers->map(fn ($user) => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'amount' => (float) $user->pivot->amount,
                ])->values(),
                'products' => $fundRequest->productShares->map(fn ($share) => [
                    'product_label' => $share->product_label,
                    'amount' => (float) $share->amount,
                ])->values(),
            ]);
    }

    /**
     * The account picker's options, each carrying the balance a new entry should
     * build on: the running balance of the account's newest transaction, or the
     * opening balance when it has none yet.
     */
    protected function accountOptions(Workspace $workspace, ?Transaction $editing = null): Collection
    {
        $accounts = Account::where('workspace_id', $workspace->id)
            ->orderBy('name')->get(['id', 'name', 'currency', 'opening_balance']);

        // Newest per account, matching the ledger's date/position ordering. One
        // small indexed lookup per account rather than a single clever query: a
        // workspace has a handful of accounts, and every "newest per group"
        // formulation MySQL offers here either runs as a dependent subquery or
        // sorts the whole ledger, both of which cost seconds on a large one.
        $latest = $accounts->mapWithKeys(fn ($account) => [
            $account->id => Transaction::where('workspace_id', $workspace->id)
                ->where('account_id', $account->id)
                ->when($editing, fn ($q) => $q->whereKeyNot($editing->getKey()))
                ->orderByDesc('date')
                ->orderByDesc('position')
                ->first(['id', 'running_balance']),
        ]);

        return $accounts->map(fn ($account) => [
            'id' => $account->id,
            'name' => $account->name,
            'currency' => $account->currency,
            'current_balance' => (float) ($latest[$account->id]?->running_balance ?? $account->opening_balance),
            'has_transactions' => $latest[$account->id] !== null,
        ]);
    }

    /**
     * Where to send the user after a save. The form may pass a `return_to` path
     * (the modal stays on its page); otherwise land on the transactions list.
     * Restricted to this workspace's own paths to avoid an open redirect.
     */
    protected function redirectAfterSave(Request $request, Workspace $workspace)
    {
        return redirect($this->safeReturnTo($request, $workspace)
            ?? route('workspaces.finance.transactions.index', $workspace->slug));
    }

    /**
     * A `return_to` path from the request, but only when it points inside this
     * workspace (guards against an open redirect). Null otherwise.
     */
    protected function safeReturnTo(Request $request, Workspace $workspace): ?string
    {
        $returnTo = $request->input('return_to');
        $prefix = "/workspaces/{$workspace->slug}/";

        return (is_string($returnTo) && str_starts_with($returnTo, $prefix)) ? $returnTo : null;
    }

    public function store(TransactionRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceTransactions->value, $workspace);
        $this->validateWorkspaceFor($workspace, $request->validated());

        $data = $request->validated();
        // charge_to / products are pivots, not columns — synced after the row exists.
        unset($data['charge_to'], $data['products']);

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

        $transaction = Transaction::create([...$data, 'workspace_id' => $workspace->id]);
        $transaction->chargeToUsers()->sync($this->chargeToPivot($request->chargeToShares()));
        $this->syncProductShares($transaction, $request->productShares());

        return $this->redirectAfterSave($request, $workspace)->with('success', 'Transaction created.');
    }

    public function update(TransactionRequest $request, Workspace $workspace, Transaction $transaction)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);
        $this->ensureOwns($workspace, $transaction);
        $this->validateWorkspaceFor($workspace, $request->validated());

        $data = $request->validated();
        unset($data['charge_to'], $data['products']);

        // Preserve existing position if not provided
        if (empty($data['position'])) {
            unset($data['position']);
        }

        $transaction->update($data);
        $transaction->chargeToUsers()->sync($this->chargeToPivot($request->chargeToShares()));
        $this->syncProductShares($transaction, $request->productShares());

        return $this->redirectAfterSave($request, $workspace)->with('success', 'Transaction updated.');
    }

    /**
     * Normalized charge-to shares as the `user_id => ['amount' => share]` map
     * belongsToMany::sync() expects.
     *
     * @param  list<array{user_id:int, amount:float}>  $shares
     * @return array<int, array{amount:float}>
     */
    protected function chargeToPivot(array $shares): array
    {
        return collect($shares)
            ->mapWithKeys(fn ($s) => [$s['user_id'] => ['amount' => $s['amount']]])
            ->all();
    }

    /**
     * Replace a transaction's product shares with the given `{product, amount}`
     * rows (the `finance_transaction_products` pivot has no natural key to sync
     * against, so the old rows are dropped and the new ones inserted).
     *
     * @param  list<array{product:string, amount:float}>  $shares
     */
    protected function syncProductShares(Transaction $transaction, array $shares): void
    {
        $transaction->productShares()->delete();

        if ($shares !== []) {
            $transaction->productShares()->createMany($shares);
        }
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
                ->with(['account', 'transactionType', 'requester:id,name', 'approver:id,name', 'chargeToUsers:users.id,users.name', 'fundRequest:id,reference_no'])
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
                'Running Balance', 'Reference No.', 'Fund Request', 'Charge To', 'Status', 'Sub Category', 'Remarks',
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
                    $txn->fundRequest?->reference_no ?? '',
                    $this->chargeToLabel($txn),
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

    /**
     * The export's "Charge To" cell. A split shows each user's share so the
     * spreadsheet keeps the same detail the ledger has.
     */
    protected function chargeToLabel(Transaction $transaction): string
    {
        $users = $transaction->chargeToUsers;

        if ($users->count() <= 1) {
            return $users->first()?->name ?? '';
        }

        return $users
            ->map(fn ($u) => $u->name.' ('.number_format((float) $u->pivot->amount, 2).')')
            ->implode(', ');
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
