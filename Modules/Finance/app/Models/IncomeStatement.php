<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeStatement extends Model
{
    protected $table = 'finance_income_statements';

    protected $fillable = [
        'workspace_id',
        'period_month',
        'total_delivered',
        'delivered_orders',
        'ad_spent',
        'total_expenses',
        'gross_profit',
        'net_profit',
        'cod_fee_rate',
        'vat_rate',
        'advisory_rate',
        'advisory_share',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'period_month' => 'date',
        'total_delivered' => 'decimal:2',
        'delivered_orders' => 'integer',
        'ad_spent' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'cod_fee_rate' => 'decimal:4',
        'vat_rate' => 'decimal:4',
        'advisory_rate' => 'decimal:4',
        'advisory_share' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function breakdown(): HasMany
    {
        return $this->hasMany(IncomeStatementExpense::class, 'income_statement_id');
    }

    /** Saved per-user slices of this statement (rebuilt on save/regenerate). */
    public function userStatements(): HasMany
    {
        return $this->hasMany(UserIncomeStatement::class, 'income_statement_id');
    }

    /**
     * Saved per-product slices of this statement — workspace-wide, across every
     * intern (rebuilt on save/regenerate).
     */
    public function productStatements(): HasMany
    {
        return $this->hasMany(ProductIncomeStatement::class, 'income_statement_id');
    }
}
