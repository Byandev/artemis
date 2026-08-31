<?php

namespace Modules\Finance\Models;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved per-user-per-product slice of an {@see IncomeStatement} — one row for
 * each seller/product pair that moved this month, plus rows with a null user or
 * product for what resolves to neither.
 *
 * The cross of {@see UserIncomeStatement} and {@see ProductIncomeStatement}:
 * either of those is this table added up along one axis, so it carries the same
 * figures and the three pages read alike. Rebuilt whenever the parent statement
 * is saved or regenerated.
 */
class UserProductIncomeStatement extends Model
{
    protected $table = 'finance_income_user_product_statements';

    protected $fillable = [
        'income_statement_id',
        'user_id',
        'user_name',
        'product_id',
        'product_name',
        'delivered_orders',
        'delivered_units',
        'delivered_amount',
        'shipped_orders',
        'total_shipping_fee',
        'ad_spent',
        'cod_fee',
        'cod_fee_vat',
        'total_bought_cogs',
        'total_bought_cogs_delivery_fee',
        'total_delivered_cogs',
        'gross_profit_delivered_cogs',
        'gross_profit_bought_cogs',
        'gross_profit_delivered_cogs_advisory_share',
        'gross_profit_bought_cogs_advisory_share',
        'gross_profit_delivered_cogs_after_advisory_share',
        'gross_profit_bought_cogs_after_advisory_share',
    ];

    protected $casts = [
        'delivered_orders' => 'integer',
        'delivered_units' => 'integer',
        'delivered_amount' => 'decimal:2',
        'shipped_orders' => 'integer',
        'total_shipping_fee' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'cod_fee_vat' => 'decimal:2',
        'total_bought_cogs' => 'decimal:2',
        'total_bought_cogs_delivery_fee' => 'decimal:2',
        'total_delivered_cogs' => 'decimal:2',
        'gross_profit_delivered_cogs' => 'decimal:2',
        'gross_profit_bought_cogs' => 'decimal:2',
        'gross_profit_delivered_cogs_advisory_share' => 'decimal:2',
        'gross_profit_bought_cogs_advisory_share' => 'decimal:2',
        'gross_profit_delivered_cogs_after_advisory_share' => 'decimal:2',
        'gross_profit_bought_cogs_after_advisory_share' => 'decimal:2',
    ];

    public function incomeStatement(): BelongsTo
    {
        return $this->belongsTo(IncomeStatement::class, 'income_statement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
