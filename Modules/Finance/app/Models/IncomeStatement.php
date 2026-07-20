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
        'total_expenses',
        'net_profit',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'period_month' => 'date',
        'total_delivered' => 'decimal:2',
        'delivered_orders' => 'integer',
        'total_expenses' => 'decimal:2',
        'net_profit' => 'decimal:2',
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
}
