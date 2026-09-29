<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line item of a fund request. `amount` is `quantity` × `unit_price`,
 * worked out on save (see FundRequestRequest::particulars()); a request's
 * particulars sum to its amount requested.
 */
class FundRequestParticular extends Model
{
    protected $table = 'finance_fund_request_particulars';

    protected $fillable = [
        'fund_request_id',
        'name',
        'quantity',
        'unit_price',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function fundRequest(): BelongsTo
    {
        return $this->belongsTo(FundRequest::class, 'fund_request_id');
    }
}
