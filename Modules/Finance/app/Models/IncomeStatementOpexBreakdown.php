<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One transaction type's share of a statement's OPEX. The rows for a statement
 * sum to its `opex`, and are rebuilt whenever it is saved or regenerated.
 *
 * The type is referenced rather than snapshotted, so deleting a transaction
 * type takes its line off statements that have already been closed.
 */
class IncomeStatementOpexBreakdown extends Model
{
    protected $table = 'finance_income_statements_opex_breakdowns';

    protected $fillable = [
        'income_statement_id',
        'transaction_type_id',
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
