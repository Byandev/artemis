<?php

namespace Modules\Finance\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved per-product slice of an {@see IncomeStatement} — one row per product
 * for the month, workspace-wide across every intern (plus one row with a null
 * product_id for delivered items that resolve to no product). Rebuilt whenever
 * the parent statement is saved or regenerated.
 *
 * Bought vs delivered are deliberately separate: `total_bought_cogs` (and the
 * freight on it) is what was purchased this month, while `total_delivered_cogs`
 * is the cost of the goods that actually went out.
 */
class ProductIncomeStatement extends Model
{
    protected $table = 'finance_income_product_statements';

    protected $fillable = [
        'income_statement_id',
        'product_id',
        'product_name',
        'delivered_orders',
        'delivered_units',
        'delivered_amount',
        'ad_spent',
        'shipped_orders',
        'total_shipping_fee',
        'cod_fee',
        'cod_fee_vat',
        'total_bought_cogs',
        'total_bought_cogs_delivery_fee',
        'total_delivered_cogs',
    ];

    protected $casts = [
        'delivered_orders' => 'integer',
        'delivered_units' => 'integer',
        'delivered_amount' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        'shipped_orders' => 'integer',
        'total_shipping_fee' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'cod_fee_vat' => 'decimal:2',
        'total_bought_cogs' => 'decimal:2',
        'total_bought_cogs_delivery_fee' => 'decimal:2',
        'total_delivered_cogs' => 'decimal:2',
    ];

    public function incomeStatement(): BelongsTo
    {
        return $this->belongsTo(IncomeStatement::class, 'income_statement_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
