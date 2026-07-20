<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeStatementExpense extends Model
{
    protected $table = 'finance_income_statement_expenses';

    protected $fillable = [
        'income_statement_id',
        'transaction_type_id',
        'type_name',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function incomeStatement(): BelongsTo
    {
        return $this->belongsTo(IncomeStatement::class, 'income_statement_id');
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }
}
