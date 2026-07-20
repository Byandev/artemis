<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionType extends Model
{
    protected $table = 'finance_transaction_types';

    protected $fillable = [
        'workspace_id',
        'name',
        'is_gross_profit_deduction',
    ];

    protected $casts = [
        'is_gross_profit_deduction' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
