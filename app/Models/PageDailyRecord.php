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
        'sales' => 'decimal:2',
        'delivered' => 'integer',
        'delivered_amount' => 'decimal:2',
        'returned' => 'integer',
        'returned_amount' => 'decimal:2',
        'returning_amount' => 'decimal:2',
        'rts_rate' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        'ad_sales' => 'decimal:2',
        'roas' => 'decimal:2',
        'ad_roas' => 'decimal:2',
        // Purchases/orders per unit of ad spend — sub-1 figures, so 2dp would
        // round them all to zero.
        'ad_cpp' => 'decimal:6',
        'cpp' => 'decimal:6',
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
