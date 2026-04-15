<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Remittance extends Model
{
    protected $table = 'finance_remittances';

    protected $fillable = [
        'workspace_id',
        'courier',
        'date',
        'reference_no',
        'gross_amount',
        'deductions',
        'net_amount',
        'notes',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Remittance $remittance) {
            $remittance->net_amount = (float) $remittance->gross_amount - (float) $remittance->deductions;
        });
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function getIsReconciledAttribute(): bool
    {
        return $this->transaction()->exists();
    }
}
