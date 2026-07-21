<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductIncomeStatementExpense extends Model
{
    protected $table = 'finance_product_income_statement_expenses';

    protected $fillable = [
        'product_income_statement_id',
        'source',
        'section',
        'transaction_type_id',
        'type_name',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function incomeStatement(): BelongsTo
    {
        return $this->belongsTo(ProductIncomeStatement::class, 'product_income_statement_id');
    }
}
