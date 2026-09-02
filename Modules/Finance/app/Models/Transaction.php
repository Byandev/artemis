<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $table = 'finance_transactions';

    protected $fillable = [
        'workspace_id',
        'account_id',
        'date',
        'description',
        'requested_by',
        'approved_by',
        'department',
        'type',
        'transaction_type',
        'transaction_type_id',
        'amount',
        'running_balance',
        'reference_no',
        'fund_request_id',
        'status',
        'position',
        'is_balance_adjustment',
        'sub_category',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_balance_adjustment' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /*
     * Relations use non-colliding names so they don't overwrite the same-named
     * foreign-key columns on serialization (a requestedBy() relation would
     * serialize to "requested_by" and clobber the integer FK the forms read).
     */

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The users this transaction is charged to. Each carries an `amount` pivot —
     * their share of the transaction, the shares summing to the full amount.
     */
    public function chargeToUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'finance_transaction_charge_to', 'transaction_id', 'user_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * The products this transaction is charged to. Each carries an `amount` — its
     * share of the transaction, the shares summing to the full amount. Feeds the
     * per-product income statement.
     */
    public function productShares(): HasMany
    {
        return $this->hasMany(TransactionProduct::class, 'transaction_id');
    }

    /**
     * The fund request this entry settles, if any. A loose reference: the link
     * is for traceability and for filling the form in, and no drawdown is
     * reconciled against the request's amount.
     */
    public function fundRequest(): BelongsTo
    {
        return $this->belongsTo(FundRequest::class, 'fund_request_id');
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function remittance(): HasOne
    {
        return $this->hasOne(Remittance::class, 'transaction_id');
    }
}
