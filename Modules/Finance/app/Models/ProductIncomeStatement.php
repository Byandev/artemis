<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductIncomeStatement extends Model
{
    protected $table = 'finance_product_income_statements';

    protected $fillable = [
        'workspace_id',
        'income_statement_id',
        'product',
        'period_month',
        'total_delivered',
        'delivered_orders',
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

    /** The parent monthly workspace income statement, if it exists. */
    public function incomeStatement(): BelongsTo
    {
        return $this->belongsTo(IncomeStatement::class, 'income_statement_id');
    }

    public function breakdown(): HasMany
    {
        return $this->hasMany(ProductIncomeStatementExpense::class, 'product_income_statement_id');
    }
}
