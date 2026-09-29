<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Products\Models\Product;

/**
 * One line item of a fund request. `amount` is `quantity` × `unit_price`,
 * worked out on save (see FundRequestRequest::particulars()); a request's
 * particulars sum to its amount requested. On an ad-spend request each row is
 * for a product, `name` holding a snapshot of that product's name.
 */
class FundRequestParticular extends Model
{
    protected $table = 'finance_fund_request_particulars';

    protected $fillable = [
        'fund_request_id',
        'product_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
