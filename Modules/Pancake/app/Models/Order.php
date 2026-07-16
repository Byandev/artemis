<?php

namespace Modules\Pancake\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\OrderTag;
use App\Models\Page;
use App\Models\ParcelJourney;
use App\Models\ShippingAddress;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use ScopesToVisibleTeams;

    protected $guarded = [];

    protected $table = 'pancake_orders';

    // Scope via the order's shop directly (shop_id) rather than through the
    // page — this also covers page-less Webcake orders (page_id is null).
    protected function visibilityTeamRelation(): string
    {
        return 'shop.teams';
    }

    protected $casts = [
        'status' => 'integer',
        'order_source' => 'integer',
    ];

    public function shippingAddress(): HasOne|\App\Models\Order
    {
        return $this->hasOne(ShippingAddress::class);
    }

    public function parcelJourney(): HasOne|Order
    {
        return $this->hasOne(ParcelJourney::class);
    }

    public function parcelJourneys(): HasMany|Order
    {
        return $this->hasMany(ParcelJourney::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function phoneNumberReports(): Order|HasMany
    {
        return $this->hasMany(OrderPhoneNumberReport::class, 'order_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(OrderTag::class);
    }

    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        $table = $builder->getModel()->getTable();

        return $builder->where("{$table}.workspace_id", $workspace->id);
    }
}
