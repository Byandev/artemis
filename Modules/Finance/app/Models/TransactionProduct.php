<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product's share of a transaction's amount. The shares of a transaction
 * always add up to its amount (see TransactionRequest::productShares()).
 */
class TransactionProduct extends Model
{
    protected $table = 'finance_transaction_products';

    protected $fillable = [
        'transaction_id',
        'product',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
