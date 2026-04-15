<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'category',
        'remittance_id',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(Remittance::class);
    }
}
