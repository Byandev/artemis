<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Products\Models\Product;

/**
 * One product's share of a fund request's amount. The shares of a request always
 * add up to its total (see FundRequestRequest::requestTotal()). `product_label`
 * is a name snapshot, so the row still reads after the product is deleted.
 */
class FundRequestProductShare extends Model
{
    protected $table = 'finance_fund_request_product_shares';

    protected $fillable = [
        'fund_request_id',
        'product_id',
        'product_label',
        'amount',
        'sort_order',
    ];

    protected $casts = [
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
