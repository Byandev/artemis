<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_user_wallet' => 'boolean',
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
                $q->selectRaw('(SELECT t2.id FROM finance_transactions t2 WHERE t2.account_id = finance_transactions.account_id AND t2.workspace_id = ? ORDER BY t2.date DESC, t2.position DESC LIMIT 1)', [$workspaceId])
                    ->from('finance_transactions')
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('account_id', $accountIds)
                    ->groupBy('account_id');
            })
            ->pluck('running_balance', 'account_id');
    }
}
