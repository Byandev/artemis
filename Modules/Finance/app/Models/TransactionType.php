<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionType extends Model
{
    /** Allowed values for the `nature` (account normal balance) column. */
    public const NATURES = ['debit', 'credit'];

    /** Where a type lands on the income statement; null = excluded (nowhere). */
    public const INCOME_STATEMENT_SECTIONS = ['cost_of_sales', 'opex'];

    protected $table = 'finance_transaction_types';

    protected $fillable = [
        'workspace_id',
        'name',
        'nature',
        'income_statement_section',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
