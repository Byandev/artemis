<?php

namespace Modules\Pancake\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily rollup of delivered/returned Pancake orders per page and per destination.
 *
 * One row per workspace / page / shop / day / province. `date` is the day
 * the order reached its outcome (delivered_at when delivered, returning_at when
 * returned), matching how RTS analytics buckets orders into a period.
 *
 * Counts and value are both kept: the heat map's RTS rate is value-based
 * (returning_amount over delivered_amount + returning_amount), the same formula
 * page_daily_records uses, so a few expensive returns register as they should.
 *
 * Built by the `build-order-location-daily-records` command, which also resolves
 * the GADM feature ids so the RTS heat map can shade polygons straight from a
 * single indexed table instead of joining orders to shipping addresses per request.
 */
class PageOrdersToLocationDailyRecord extends Model
{
    use ScopesToVisibleTeams;

    protected $table = 'page_orders_to_location_daily_records';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'orders' => 'integer',
        'sales' => 'decimal:2',
        'delivered_count' => 'integer',
        'delivered_amount' => 'decimal:2',
        'returning_count' => 'integer',
        'returning_amount' => 'decimal:2',
    ];

    /** shop_id is denormalised onto the row, so teams are reached the same way an Order reaches them. */
    protected function visibilityTeamRelation(): string
    {
        return 'shop.teams';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
