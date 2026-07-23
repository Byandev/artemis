<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIncomeStatementProduct extends Model
{
    protected $table = 'finance_user_income_statement_products';

    protected $fillable = [
        'user_income_statement_id',
        'product',
        'revenue',
        'orders',
        'cogs',
        'shipping',
        'cod',
        'vat',
        'tagged_expense',
        'gross_profit',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
        'orders' => 'integer',
        'cogs' => 'decimal:2',
        'shipping' => 'decimal:2',
        'cod' => 'decimal:2',
        'vat' => 'decimal:2',
        'tagged_expense' => 'decimal:2',
        'gross_profit' => 'decimal:2',
    ];

    public function userIncomeStatement(): BelongsTo
    {
        return $this->belongsTo(UserIncomeStatement::class, 'user_income_statement_id');
    }
}
