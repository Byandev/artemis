<?php

namespace Modules\Finance\Models;

use App\Models\Page;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single "FOR SCALING / RUNNING" line on an Ad Spent fund request:
 * one product, the page its budget was read from, and the maths for the row.
 */
class FundRequestItem extends Model
{
    protected $table = 'finance_request_fund_items';

    protected $fillable = [
        'fund_request_id',
        'product_id',
        'page_id',
        'item_label',
        'creatives_running',
        'budget_per_day',
        'days',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'creatives_running' => 'integer',
        'budget_per_day' => 'decimal:2',
        'days' => 'decimal:2',
        'total' => 'decimal:2',
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

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
