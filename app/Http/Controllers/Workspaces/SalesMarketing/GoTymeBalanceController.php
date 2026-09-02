<?php

namespace App\Http\Controllers\Workspaces\SalesMarketing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\SalesMarketing\GoTymeWalletRequest;
use App\Http\Requests\Workspaces\SalesMarketing\LiquidationEntryRequest;
use App\Http\Requests\Workspaces\SalesMarketing\UpdateWalletDailyBalanceRequest;
use App\Models\Department;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;

/**
 * Go Tyme Balance — a sibling page under Sales & Marketing. It rides the S&M
 * module flag like the rest of the group and carries its own grants.
 *
 * The page is a day-by-day grid: one row per wallet, one column per date in a
 * trailing window. Every cell is derived — the wallet's balance at the close of
 * that day, walked from its opening balance through its liquidation ledger — so
 * the grid and the ledger cannot disagree. A row starts where its wallet's
 * history starts (the day it was created, or an earlier back-dated entry); the
 * days before that read blank rather than zero.
 *
 * Cells up to today are still typeable, blank ones included, but a typed figure
 * is not stored as one: the grid posts a correction to that day's ledger for
 * the gap between what the entries close it at and what was entered. So a
 * balance still comes from exactly one place, every day after a correction
 * re-flows, and the correction itself is a visible, undoable line on the
 * liquidation summary. Typing into a blank day is how a balance from before the
 * wallet was created gets recorded — it pulls the row's start back to that day.
 *
 * The wallets themselves are ordinary Finance accounts (same table), flagged
 * `is_user_wallet` so they stay out of Finance → Accounts and out of Finance
 * transactions.
 */
class GoTymeBalanceController extends Controller
{
    use AuthorizesRequests;

    /** Columns in the grid when no range is asked for, ending today. */
    private const DEFAULT_DAYS = 8;

    /** Ceiling on a hand-picked range — a column per date times a row per
     *  wallet gets heavy fast. */
    private const MAX_DAYS = 92;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorizeAccess($workspace);

        [$start, $end] = $this->resolveRange($request);
        $dates = $this->datesInRange($start, $end);

        $accounts = Account::where('workspace_id', $workspace->id)
            ->wallets()
            ->orderBy('name')
            // Main above Backup for the same holder, then id so equal rows keep
            // a stable order between requests.
            ->orderByRaw("CASE wallet_type WHEN 'main' THEN 0 WHEN 'backup' THEN 1 ELSE 2 END")
            ->orderBy('id')
            // The entry count rides along so a delete can say what it takes
            // with it — the ledger cascades off the account row. The earliest
            // entry sets where the row starts when something was back-dated in
            // ahead of the wallet's own creation.
            ->withCount('transactions')
            ->withMin('transactions as first_entry_on', 'date')
            ->get(['id', 'name', 'opening_balance', 'currency', 'notes', 'is_active', 'is_user_wallet', 'wallet_type', 'created_at']);

        $accountIds = $accounts->pluck('id');

        // Where each wallet stood the day before the window opened: everything
        // its ledger posted before the range, added to the opening balance below.
        // Plucked off the results, not the query: pluck on the builder would
        // swap out the select and lose the SUM this reads back.
        $carried = $this->signedMovement($workspace, $accountIds)
            ->where('date', '<', $start)
            ->groupBy('account_id')
            ->get()
            ->pluck('movement', 'account_id');

        // The same signed movement inside the window, a figure per day — one
        // grouped query the walk below accumulates over.
        $movements = $this->signedMovement($workspace, $accountIds)
            ->addSelect('date')
            ->whereBetween('date', [$start, $end])
            ->groupBy('account_id', 'date')
            ->get()
            ->groupBy('account_id')
            ->map(fn ($days) => $days->mapWithKeys(fn ($row) => [
                Carbon::parse($row->date)->toDateString() => (float) $row->movement,
            ]));

        $rows = $accounts->map(function (Account $account) use ($dates, $carried, $movements) {
            // Every cell is the balance at the close of its day, so the walk
            // starts from where the wallet already stood and each date adds only
            // what its own entries moved.
            $balance = (float) $account->opening_balance + (float) $carried->get($account->id, 0);
            $byDay = $movements->get($account->id, collect());
            // Nothing to show before the wallet's history starts: those days
            // are blank rather than zero, and rather than its opening balance.
            // Usually the day it was created, but a back-dated entry pulls the
            // start back to itself rather than leaving the row disagreeing with
            // its own ledger.
            $openedOn = collect([
                $account->created_at->toDateString(),
                $account->first_entry_on,
            ])->filter()->min();

            return [
                ...$account->only(['id', 'name', 'currency', 'notes', 'is_active', 'is_user_wallet']),
                'opening_balance' => (float) $account->opening_balance,
                'wallet_type' => $account->wallet_type?->value,
                'opened_on' => $openedOn,
                'entry_count' => (int) $account->transactions_count,
                'days' => $dates->mapWithKeys(function (string $date) use (&$balance, $byDay, $openedOn) {
                    $balance += $byDay->get($date, 0.0);

                    return [$date => $date < $openedOn ? null : round($balance, 2)];
                })->all(),
            ];
        });

        return Inertia::render('workspaces/sales-marketing/go-tyme-balance/index', [
            'workspace' => $workspace,
            'dates' => $dates->map(fn (string $date) => [
                'date' => $date,
                'label' => Carbon::parse($date)->format('M j'),
                'is_today' => $date === Carbon::today()->toDateString(),
            ]),
            'rows' => $rows,
            'query' => ['start' => $start, 'end' => $end],
            // Column totals over the wallets that were open on the day. A
            // column before every wallet existed totals null, so it renders as
            // an em dash rather than ₱0.
            'totals' => $dates->mapWithKeys(function (string $date) use ($rows) {
                $open = $rows->pluck("days.{$date}")->filter(fn ($v) => $v !== null);

                return [$date => $open->isEmpty() ? null : round((float) $open->sum(), 2)];
            }),
            'canManage' => $request->user()->hasPermission(
                Permission::ManageGoTymeBalance->value,
                $workspace,
            ),
        ]);
    }

    public function store(GoTymeWalletRequest $request, Workspace $workspace)
    {
        $this->authorizeAccess($workspace);

        $this->authorize(Permission::ManageGoTymeBalance->value, $workspace);

        Account::create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            // What makes this a wallet rather than a company account: set here
            // and nowhere else, so only this page can mint one.
            'is_user_wallet' => true,
        ]);

        return redirect()
            ->route('workspaces.sales-marketing.go-tyme-balance', $workspace)
            ->with('success', 'Account created.');
    }

    /**
     * Edit a wallet. The opening balance is editable, and moving it moves every
     * figure the ledger ever showed — so the running balances are re-flowed
     * here rather than left to drift from the figure they started at.
     */
    public function update(GoTymeWalletRequest $request, Workspace $workspace, Account $account)
    {
        $this->authorizeManage($workspace, $account);

        $account->update($request->validated());

        $account->resequenceRunningBalances();

        return redirect()
            ->route('workspaces.sales-marketing.go-tyme-balance', $workspace)
            ->with('success', 'Account updated.');
    }

    /**
     * Delete a wallet. Its liquidation entries go with it — the ledger's
     * foreign key cascades — so the page asks for that in so many words,
     * naming the count, before it gets here.
     */
    public function destroy(Request $request, Workspace $workspace, Account $account)
    {
        $this->authorizeManage($workspace, $account);

        $account->delete();

        return redirect()
            ->route('workspaces.sales-marketing.go-tyme-balance', $workspace)
            ->with('success', 'Account deleted.');
    }

    /**
     * A wallet's liquidation summary. The ledger is ordinary finance
     * transactions against this account — no separate store — read back in
     * ledger order with their running balance as the "actual balance".
     *
     * Sorting, filtering, the date range and paging are all done on the page,
     * so none of them costs a request. The totals are the exception: they are
     * their own variable off their own aggregate query, served on this same
     * response — the page falls back to summing the visible rows only while a
     * filter is narrowing them.
     */
    public function show(Request $request, Workspace $workspace, Account $account): Response
    {
        $this->authorizeAccess($workspace);

        $this->guardWallet($workspace, $account);

        $entries = $this->ledger($account);
        $totals = $this->totals($account);

        return Inertia::render('workspaces/sales-marketing/go-tyme-balance/show', [
            'workspace' => $workspace,
            'account' => [
                ...$account->only(['id', 'name', 'currency', 'notes', 'is_active']),
                // Shown as its own tile: the ledger's running balance starts
                // here, so leaving it off made the arithmetic unaccountable.
                'opening_balance' => (float) $account->opening_balance,
                'wallet_type' => $account->wallet_type?->value,
            ],
            'entries' => $entries->map(fn (Transaction $entry) => [
                'id' => $entry->id,
                'date' => $entry->date,
                // The ledger's "Transaction" column — Funds, Ad Spent, Pondo.
                'transaction_type_id' => $entry->transaction_type_id,
                'transaction_type_name' => $entry->transactionType?->name,
                // The ledger's "Type Expenses" — FB ADS, Funds from ate MY.
                'description' => $entry->description,
                'department' => $entry->department,
                'type' => $entry->type,
                'amount' => (float) $entry->amount,
                'notes' => $entry->notes,
                'running_balance' => (float) $entry->running_balance,
            ])->values(),
            'totals' => $totals,
            // The workspace's transaction types, for the Transaction select —
            // the same catalogue the Finance transaction form offers.
            'transactionTypes' => TransactionType::where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            // The workspace's saved (active) departments, for the Department
            // select — same list the Finance transaction form offers.
            'departments' => Department::ofWorkspace($workspace)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
            'canManage' => $request->user()->hasPermission(
                Permission::ManageGoTymeBalance->value,
                $workspace,
            ),
        ]);
    }

    public function storeEntry(LiquidationEntryRequest $request, Workspace $workspace, Account $account)
    {
        $this->authorizeManage($workspace, $account);

        $validated = $request->validated();

        Transaction::create([
            ...$validated,
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            // Appended to the end of its day; resequencing then sets the balance.
            'position' => $this->nextPosition($account, $validated['date']),
        ]);

        $account->resequenceRunningBalances();

        return redirect()->back()->with('success', 'Entry added.');
    }

    public function updateEntry(LiquidationEntryRequest $request, Workspace $workspace, Account $account, Transaction $entry)
    {
        $this->authorizeManage($workspace, $account);

        $this->guardEntry($account, $entry);

        $validated = $request->validated();

        // Moving an entry to another day puts it at the end of that day.
        if ($validated['date'] !== $entry->date) {
            $validated['position'] = $this->nextPosition($account, $validated['date']);
        }

        $entry->update($validated);

        $account->resequenceRunningBalances();

        return redirect()->back()->with('success', 'Entry updated.');
    }

    public function destroyEntry(Request $request, Workspace $workspace, Account $account, Transaction $entry)
    {
        $this->authorizeManage($workspace, $account);

        $this->guardEntry($account, $entry);

        $entry->delete();

        $account->resequenceRunningBalances();

        return redirect()->back()->with('success', 'Entry deleted.');
    }

    /**
     * This account's transactions in ledger order — the order the running
     * balance accumulates in.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    private function ledger(Account $account)
    {
        return $account->transactions()
            ->with('transactionType:id,name')
            ->orderBy('date')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * The ledger's totals, as their own aggregate rather than a walk over the
     * rows the page already has. One grouped query — the database sums it,
     * PHP just shapes the answer.
     *
     * @return array{total_credit: float, credit_count: int, total_debit: float, debit_count: int, actual_balance: float, entry_count: int, first_date: string|null, last_date: string|null}
     */
    private function totals(Account $account): array
    {
        $bySide = $account->transactions()
            ->selectRaw('type, SUM(amount) AS total, COUNT(*) AS entries, MIN(date) AS first_date, MAX(date) AS last_date')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $credit = $bySide->get('in');
        $debit = $bySide->get('out');

        return [
            'total_credit' => round((float) ($credit->total ?? 0), 2),
            'credit_count' => (int) ($credit->entries ?? 0),
            'total_debit' => round((float) ($debit->total ?? 0), 2),
            'debit_count' => (int) ($debit->entries ?? 0),
            // Opening balance included, matching what the ledger's running
            // balance column carries.
            'actual_balance' => round(
                (float) $account->opening_balance
                    + (float) ($credit->total ?? 0)
                    - (float) ($debit->total ?? 0),
                2,
            ),
            'entry_count' => (int) ($credit->entries ?? 0) + (int) ($debit->entries ?? 0),
            'first_date' => collect([$credit->first_date ?? null, $debit->first_date ?? null])->filter()->min(),
            'last_date' => collect([$credit->last_date ?? null, $debit->last_date ?? null])->filter()->max(),
        ];
    }

    /**
     * Next free slot on a given day, so a new entry lands after that day's
     * others. `$ignoreId` leaves out a row that is itself being moved, which
     * would otherwise count as its own predecessor.
     */
    private function nextPosition(Account $account, string $date, ?int $ignoreId = null): int
    {
        return (int) $account->transactions()
            ->where('date', $date)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->max('position') + 1;
    }

    private function guardEntry(Account $account, Transaction $entry): void
    {
        abort_unless($entry->account_id === $account->id, 404);
    }

    private function authorizeManage(Workspace $workspace, Account $account): void
    {
        $this->authorizeAccess($workspace);

        $this->authorize(Permission::ManageGoTymeBalance->value, $workspace);

        $this->guardWallet($workspace, $account);
    }

    /**
     * Write one cell of the grid: the balance that day should close at.
     *
     * The figure is not stored — a correction is posted to the wallet's ledger
     * for the difference, so the grid stays derived off the entries and the
     * change shows up on the liquidation summary where it can be read and
     * undone. One correction per day: re-typing a cell rewrites its own rather
     * than stacking a second. Clearing the cell deletes it, handing the day
     * back to the entries alone.
     */
    public function updateBalance(UpdateWalletDailyBalanceRequest $request, Workspace $workspace, Account $account)
    {
        $this->authorizeManage($workspace, $account);

        ['date' => $date, 'balance' => $target] = $request->validated();

        // A day the grid shows blank is typeable too: the correction posted
        // there pulls the wallet's history back to it, which is how a balance
        // from before the wallet was created gets recorded.
        $correction = $account->transactions()
            ->where('date', $date)
            ->where('is_balance_adjustment', true)
            ->first();

        // Where the day closes on the entries alone — this day's own
        // correction left out, since it is the thing being rewritten.
        $closing = (float) $account->opening_balance + (float) $account->transactions()
            ->where('date', '<=', $date)
            ->when($correction, fn ($q) => $q->whereKeyNot($correction->id))
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) AS movement")
            ->value('movement');

        $delta = $target === null ? 0.0 : round((float) $target - $closing, 2);

        // Cleared, or the day already lands there on its own: nothing to carry.
        if ($target === null || abs($delta) < 0.005) {
            $correction?->delete();
            $account->resequenceRunningBalances();

            return redirect()->back();
        }

        $attributes = [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'date' => $date,
            'transaction_type_id' => $this->adjustmentType($workspace)->id,
            'description' => 'Balance adjustment',
            'type' => $delta > 0 ? 'in' : 'out',
            'amount' => abs($delta),
            'notes' => 'Recorded from the Go Tyme Balance grid.',
            'is_balance_adjustment' => true,
            // Last on its day, so the ledger reads as the correction that
            // closed the day rather than a line buried mid-way through it.
            'position' => $this->nextPosition($account, $date, $correction?->id),
        ];

        $correction
            ? $correction->update($attributes)
            : Transaction::create($attributes);

        $account->resequenceRunningBalances();

        return redirect()->back();
    }

    /**
     * The workspace's transaction type for a grid correction, made on first
     * use. An ordinary type, so it filters and reports like any other.
     */
    private function adjustmentType(Workspace $workspace): TransactionType
    {
        return TransactionType::firstOrCreate([
            'workspace_id' => $workspace->id,
            'name' => 'Balance Adjustment',
        ]);
    }

    /**
     * A query over these wallets' ledgers carrying each one's signed movement —
     * credits less debits. Grouped by account alone it gives a wallet's net
     * change over a span; grouped by account and date, its change on each day.
     *
     * @param  Collection<int, int>  $accountIds
     */
    private function signedMovement(Workspace $workspace, Collection $accountIds): Builder
    {
        return Transaction::where('workspace_id', $workspace->id)
            ->whereIn('account_id', $accountIds)
            ->selectRaw("account_id, SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END) AS movement");
    }

    /**
     * The range the grid covers: whatever was asked for, else the trailing
     * eight days ending today. Reversed if handed back to front, and clamped so
     * one request cannot ask for years of columns.
     *
     * @return array{0: string, 1: string} [start, end] as Y-m-d
     */
    private function resolveRange(Request $request): array
    {
        $end = $this->parseDate($request->input('end')) ?? Carbon::today();
        $start = $this->parseDate($request->input('start'))
            ?? $end->copy()->subDays(self::DEFAULT_DAYS - 1);

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) >= self::MAX_DAYS) {
            $start = $end->copy()->subDays(self::MAX_DAYS - 1);
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Every date in the inclusive range, oldest first.
     *
     * @return Collection<int, string>
     */
    private function datesInRange(string $start, string $end): Collection
    {
        $dates = collect();

        for ($d = Carbon::parse($start); $d->lte($end); $d->addDay()) {
            $dates->push($d->toDateString());
        }

        return $dates;
    }

    /** The grid only writes to this workspace's wallets. */
    private function guardWallet(Workspace $workspace, Account $account): void
    {
        abort_unless(
            $account->workspace_id === $workspace->id && $account->is_user_wallet,
            404,
        );
    }

    private function authorizeAccess(Workspace $workspace): void
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewGoTymeBalance->value, $workspace);
    }
}
