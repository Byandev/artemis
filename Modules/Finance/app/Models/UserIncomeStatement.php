<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved per-user slice of an {@see IncomeStatement} — one row per intern for
 * the month, plus one row with a null user_id for orders whose intern cell
 * resolves to nobody. Rebuilt whenever the parent statement is saved or
 * regenerated.
 *
 * Deliberately the same shape as {@see ProductIncomeStatement}: the two answer
 * the same questions cut different ways, so they carry the same figures and the
 * pages that read them look alike.
 */
class UserIncomeStatement extends Model
{
    protected $table = 'finance_income_user_statements';

    protected $fillable = [
        'income_statement_id',
        'user_id',
        'user_name',
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
        'opex',
        'opex_share_percentage',
        'net_profit_delivered_cogs',
        'net_profit_bought_cogs',
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
        'opex' => 'decimal:2',
        // A percentage (0-100), not a fraction — see the migration.
        'opex_share_percentage' => 'decimal:6',
        'net_profit_delivered_cogs' => 'decimal:2',
        'net_profit_bought_cogs' => 'decimal:2',
    ];

    public function incomeStatement(): BelongsTo
    {
        return $this->belongsTo(IncomeStatement::class, 'income_statement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
