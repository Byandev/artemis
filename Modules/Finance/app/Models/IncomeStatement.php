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
        'delivered_units',
        'shipped_orders',
        'total_shipping_fee',
        'cod_fee',
        'cod_fee_vat',
        'total_bought_cogs',
        'total_bought_cogs_delivery_fee',
        'total_delivered_cogs',
        'gross_profit_delivered_cogs',
        'gross_profit_bought_cogs',
        'gross_profit_delivered_cogs_advisory_share',
        'gross_profit_bought_cogs_advisory_share',
        'gross_profit_delivered_cogs_after_advisory_share',
        'gross_profit_bought_cogs_after_advisory_share',
        'ad_spent',
        'total_expenses',
        'opex',
        'net_profit_delivered_cogs',
        'net_profit_bought_cogs',
        'gross_profit',
        'net_profit',
        'cod_fee_rate',
        'vat_rate',
        'advisory_rate',
        'advisory_delivered_rate',
        'advisory_share_on_delivered',
        'advisory_share',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'period_month' => 'date',
        'total_delivered' => 'decimal:2',
        'delivered_orders' => 'integer',
        'delivered_units' => 'integer',
        'shipped_orders' => 'integer',
        'total_shipping_fee' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'cod_fee_vat' => 'decimal:2',
        'total_bought_cogs' => 'decimal:2',
        'total_bought_cogs_delivery_fee' => 'decimal:2',
        'total_delivered_cogs' => 'decimal:2',
        'gross_profit_delivered_cogs' => 'decimal:2',
        'gross_profit_bought_cogs' => 'decimal:2',
        'gross_profit_delivered_cogs_advisory_share' => 'decimal:2',
        'gross_profit_bought_cogs_advisory_share' => 'decimal:2',
        'gross_profit_delivered_cogs_after_advisory_share' => 'decimal:2',
        'gross_profit_bought_cogs_after_advisory_share' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'opex' => 'decimal:2',
        'net_profit_delivered_cogs' => 'decimal:2',
        'net_profit_bought_cogs' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'cod_fee_rate' => 'decimal:4',
        'vat_rate' => 'decimal:4',
        'advisory_rate' => 'decimal:4',
        'advisory_delivered_rate' => 'decimal:4',
        'advisory_share_on_delivered' => 'decimal:2',
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

    /** The statement's OPEX split by transaction type (rebuilt on save/regenerate). */
    public function opexBreakdown(): HasMany
    {
        return $this->hasMany(IncomeStatementOpexBreakdown::class, 'income_statement_id');
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
