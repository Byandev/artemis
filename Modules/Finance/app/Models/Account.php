<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Modules\Finance\Enums\WalletType;

class Account extends Model
{
    protected $table = 'finance_accounts';

    protected $fillable = [
        'workspace_id',
        'name',
        'opening_balance',
        'currency',
        'notes',
        'is_active',
        'is_user_wallet',
        'wallet_type',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_user_wallet' => 'boolean',
        'wallet_type' => WalletType::class,
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Per-user wallets, created from the Go Tyme Balance page. */
    public function scopeWallets(Builder $query): Builder
    {
        return $query->where('is_user_wallet', true);
    }

    /** Company accounts — everything Finance → Accounts manages. */
    public function scopeExcludingWallets(Builder $query): Builder
    {
        return $query->where('is_user_wallet', false);
    }

    /**
     * Rewrite this account's stored running balances from scratch, walking its
     * transactions in ledger order from the opening balance. Cheap (one
     * account's rows) and the only way a back-dated entry re-flows every figure
     * after it.
     */
    public function resequenceRunningBalances(): void
    {
        // Runs from the opening balance, not from zero: the rest of Finance
        // reads running_balance as the account's actual balance at that entry,
        // and falls back to opening_balance when there are no entries at all.
        // Starting at zero made a wallet drop from its opening figure the
        // moment its first entry landed.
        $balance = (float) $this->opening_balance;

        $this->transactions()
            ->orderBy('date')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'type', 'amount', 'running_balance'])
            ->each(function (Transaction $transaction) use (&$balance) {
                $balance += $transaction->type === 'in'
                    ? (float) $transaction->amount
                    : -(float) $transaction->amount;

                $rounded = round($balance, 2);

                // Only touch rows whose figure actually moved — but a row that
                // has never been written carries null, which casts to the same
                // 0.0 as a balance of zero and would otherwise be skipped.
                if ($transaction->running_balance === null
                    || (float) $transaction->running_balance !== $rounded) {
                    $transaction->forceFill(['running_balance' => $rounded])->save();
                }
            });
    }

    /**
     * Current balance per account: the running_balance of each account's latest
     * transaction. Accounts with no transactions are absent — callers fall back
     * to the opening balance.
     *
     * @param  iterable<int>  $accountIds
     * @return Collection<int, float> keyed by account id
     */
    public static function currentBalances(int $workspaceId, iterable $accountIds): Collection
    {
        $accountIds = collect($accountIds);

        if ($accountIds->isEmpty()) {
            return collect();
        }

        return Transaction::where('workspace_id', $workspaceId)
            ->whereIn('account_id', $accountIds)
            ->whereIn('id', function ($q) use ($workspaceId, $accountIds) {
                $q->selectRaw('(SELECT t2.id FROM finance_transactions t2 WHERE t2.account_id = finance_transactions.account_id AND t2.workspace_id = ? ORDER BY t2.date DESC, t2.position DESC, t2.id DESC LIMIT 1)', [$workspaceId])
                    ->from('finance_transactions')
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('account_id', $accountIds)
                    ->groupBy('account_id');
            })
            ->pluck('running_balance', 'account_id');
    }
}
