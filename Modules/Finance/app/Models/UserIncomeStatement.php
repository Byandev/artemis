<?php

namespace Modules\Finance\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\GencysERP\Models\Intern;

class UserIncomeStatement extends Model
{
    protected $table = 'finance_user_income_statements';

    protected $fillable = [
        'workspace_id',
        'income_statement_id',
        'gencys_intern_id',
        'user_id',
        'intern_name',
        'period_month',
        'total_delivered',
        'delivered_orders',
        'total_cogs',
        'total_shipping',
        'total_cod',
        'total_vat',
        'total_tagged',
        'gross_profit',
        'total_opex',
        'advisory_rate',
        'advisory_share',
        'cod_fee_rate',
        'vat_rate',
        'net_profit',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'period_month' => 'date',
        'total_delivered' => 'decimal:2',
        'delivered_orders' => 'integer',
        'total_cogs' => 'decimal:2',
        'total_shipping' => 'decimal:2',
        'total_cod' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_tagged' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'total_opex' => 'decimal:2',
        'advisory_rate' => 'decimal:4',
        'advisory_share' => 'decimal:2',
        'cod_fee_rate' => 'decimal:4',
        'vat_rate' => 'decimal:4',
        'net_profit' => 'decimal:2',
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

    public function intern(): BelongsTo
    {
        return $this->belongsTo(Intern::class, 'gencys_intern_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Per-product gross P&L rows. */
    public function productBreakdown(): HasMany
    {
        return $this->hasMany(UserIncomeStatementProduct::class, 'user_income_statement_id');
    }

    /** User-level OPEX buckets (untagged charged transactions). */
    public function expenseBreakdown(): HasMany
    {
        return $this->hasMany(UserIncomeStatementExpense::class, 'user_income_statement_id');
    }
}
