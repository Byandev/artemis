<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An attachment a fund request can call for. It belongs to the workspace and can
 * be called for by any number of its transaction types; no timestamps.
 */
class FundRequestAttachmentRequirement extends Model
{
    protected $table = 'finance_fund_request_attachment_requirements';

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
     * The transaction types whose fund requests call for this attachment.
     */
    public function transactionTypes(): BelongsToMany
    {
        return $this->belongsToMany(TransactionType::class, 'finance_fund_request_transaction_type_attachments', 'attachment_requirement_id', 'transaction_type_id');
    }
}
