<?php

namespace Modules\Pancake\Models;

use App\Models\Page;
use App\Models\ParcelJourney;
use App\Models\ShippingAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $guarded = [];

    protected $table = 'pancake_orders';

    protected $casts = [
        'status' => 'integer',
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

    public function phoneNumberReports(): Order|HasMany
    {
        return $this->hasMany(OrderPhoneNumberReport::class, 'order_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
