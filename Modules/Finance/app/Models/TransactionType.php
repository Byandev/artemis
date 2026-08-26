<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionType extends Model
{
    /** Allowed values for the `nature` (account normal balance) column. */
    public const NATURES = ['debit', 'credit'];

    /** Where a type lands on the income statement; null = excluded (nowhere). */
    public const INCOME_STATEMENT_SECTIONS = ['cost_of_sales', 'opex'];

    /**
     * How this type's company-wide OPEX pool is split across products on the
     * per-user statement: each product carries the slice matching its share of
     * the chosen company metric. Which metric fits depends on the cost — a CSR's
     * salary tracks every order taken, warehouse and courier costs track parcels
     * actually delivered, and a revenue-linked fee tracks peso volume.
     *
     * Keyed by stored value => label shown in the UI.
     */
    public const ALLOCATION_BASES = [
        self::BASIS_DELIVERED_PARCELS => 'Delivered parcels',
        self::BASIS_TOTAL_ORDERS => 'Total orders',
        self::BASIS_DELIVERED_REVENUE => 'Delivered revenue',
    ];

    /** Delivered parcels in the month (parcel_updated_date). The default. */
    public const BASIS_DELIVERED_PARCELS = 'delivered_parcels';

    /** Every order placed in the month (order_date), whatever its parcel status. */
    public const BASIS_TOTAL_ORDERS = 'total_orders';

    /** Delivered peso revenue in the month. */
    public const BASIS_DELIVERED_REVENUE = 'delivered_revenue';

    public const DEFAULT_ALLOCATION_BASIS = self::BASIS_DELIVERED_PARCELS;

    protected $table = 'finance_transaction_types';

    protected $fillable = [
        'workspace_id',
        'name',
        'nature',
        'income_statement_section',
        'opex_allocation_basis',
    ];

    /** The effective basis: the configured one, or the default when unset. */
    public function allocationBasis(): string
    {
        return $this->opex_allocation_basis ?: self::DEFAULT_ALLOCATION_BASIS;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
