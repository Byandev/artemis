<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A checklist item a fund request is checked against. Each transaction type has
 * its own set; no timestamps.
 */
class FundRequestChecklist extends Model
{
    protected $table = 'finance_fund_request_checklists';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'transaction_type_id',
        'name',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }
}
