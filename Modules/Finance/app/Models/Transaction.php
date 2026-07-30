<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'product',
        'amount',
        'running_balance',
        'reference_no',
        'status',
        'position',
        'sub_category',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function remittance(): HasOne
    {
        return $this->hasOne(Remittance::class, 'transaction_id');
    }
}
