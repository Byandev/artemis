<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A checklist item a fund request can be checked against. It belongs to the
 * workspace and can be on the checklist of any number of its transaction
 * types; no timestamps.
 */
class FundRequestChecklistRequirement extends Model
{
    protected $table = 'finance_fund_request_checklist_requirements';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'name',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The transaction types whose checklist carries this item.
     */
    public function transactionTypes(): BelongsToMany
    {
        return $this->belongsToMany(TransactionType::class, 'finance_fund_request_transaction_type_checklists', 'checklist_requirement_id', 'transaction_type_id');
    }
}
