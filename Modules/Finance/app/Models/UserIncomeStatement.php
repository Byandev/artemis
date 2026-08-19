<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved per-user slice of an {@see IncomeStatement} (one row per user, plus one
 * "Unassigned" row with a null user_id). Rebuilt whenever the parent statement is
 * saved/regenerated; the per-user pages read these instead of recomputing.
 */
class UserIncomeStatement extends Model
{
    protected $table = 'finance_user_income_statements';

    protected $fillable = [
        'income_statement_id',
        'user_id',
        'user_name',
        'orders',
        'delivered',
        'cost_of_sales',
        'gross_profit',
        'advisory',
        'opex',
        'net_profit',
        'cod_fee_rate',
        'vat_rate',
        'advisory_rate',
        'gencys_partner',
        'lines',
    ];

    protected $casts = [
        'orders' => 'integer',
        'delivered' => 'decimal:2',
        'cost_of_sales' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'advisory' => 'decimal:2',
        'opex' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'cod_fee_rate' => 'decimal:4',
        'vat_rate' => 'decimal:4',
        'advisory_rate' => 'decimal:4',
        'gencys_partner' => 'boolean',
        'lines' => 'array',
    ];

    public function incomeStatement(): BelongsTo
    {
        return $this->belongsTo(IncomeStatement::class);
    }
}
