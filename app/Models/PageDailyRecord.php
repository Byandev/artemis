<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Daily per-page performance, unified across sources.
 *
 * `source` distinguishes the pipeline ('gencys' | 'artemis') and the page is
 * polymorphic — an Artemis Pancake Page or a Gencys Page — via page_type /
 * page_id. `sales` is the Pancake purchase value, so ROAS = sales / ad_spent;
 * `ad_sales` is Meta's attributed purchase value, so ad_roas = ad_sales / ad_spent.
 */
class PageDailyRecord extends Model
{
    public const SOURCE_GENCYS = 'gencys';

    public const SOURCE_ARTEMIS = 'artemis';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'orders' => 'integer',
        // The day's orders opened up to their lines: units sold, and what those
        // goods cost. Null cost means none was recorded, not a cost of zero.
        'item_quantity' => 'integer',
        'order_cogs' => 'decimal:2',
        'sales' => 'decimal:2',
        'delivered' => 'integer',
        'delivered_amount' => 'decimal:2',
        'returned' => 'integer',
        'returned_amount' => 'decimal:2',
        'returning_amount' => 'decimal:2',
        'rts_rate' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        // The planned daily spend for the page, snapshotted from
        // page_daily_budget_records — null when the page had none on record.
        'ad_spend_budget' => 'decimal:2',
        'ad_sales' => 'decimal:2',
        'ad_purchases' => 'integer',
        'roas' => 'decimal:2',
        'ad_roas' => 'decimal:2',
        // Cost per purchase — ad spend over the Meta / Pancake counts.
        'ad_cpp' => 'decimal:2',
        'cpp' => 'decimal:2',
    ];

    /** The Artemis Pancake Page or Gencys Page this row belongs to. */
    public function page(): MorphTo
    {
        return $this->morphTo();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
