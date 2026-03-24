<?php

namespace Modules\Pancake\Models;

use App\Models\Page;
use App\Models\ParcelJourney;
use App\Models\ShippingAddress;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $table = 'pancake_orders';

    public function shippingAddress(): \Illuminate\Database\Eloquent\Relations\HasOne|\App\Models\Order
    {
        return $this->hasOne(ShippingAddress::class);
    }

    public function parcelJourney(): \Illuminate\Database\Eloquent\Relations\HasOne|Order
    {
        return $this->hasOne(ParcelJourney::class);
    }

    public function parcelJourneys(): \Illuminate\Database\Eloquent\Relations\HasMany|Order
    {
        return $this->hasMany(ParcelJourney::class);
    }

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function phoneNumberReports(): Order|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderPhoneNumberReport::class, 'order_id', 'id');
    }
}
