<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Pancake\Models\CityOrderSummary;

class ShippingAddress extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function cityOrderSummary(): HasOne|ShippingAddress
    {
        return $this->hasOne(CityOrderSummary::class, 'district_id', 'district_id');
    }
}
