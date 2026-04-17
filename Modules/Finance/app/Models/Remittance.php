<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remittance extends Model
{
    protected $table = 'finance_remittances';

    protected $fillable = [
        'workspace_id',
        'courier',
        'soa_number',
        'billing_date_from',
        'billing_date_to',
        'gross_cod',
        'cod_fee',
        'cod_fee_vat',
        'shipping_fee',
        'return_shipping',
        'net_amount',
        'status',
        'transaction_id',
        'notes',
    ];

    protected $casts = [
        'billing_date_from' => 'date',
        'billing_date_to' => 'date',
        'gross_cod' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'cod_fee_vat' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'return_shipping' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function getIsReconciledAttribute(): bool
    {
        return $this->transaction_id !== null;
    }
}
