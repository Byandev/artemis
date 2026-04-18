<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $table = 'finance_transactions';

    protected $fillable = [
        'workspace_id',
        'account_id',
        'date',
        'description',
        'type',
        'transaction_type',
        'amount',
        'running_balance',
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

    public function remittance(): HasOne
    {
        return $this->hasOne(Remittance::class, 'transaction_id');
    }
}
