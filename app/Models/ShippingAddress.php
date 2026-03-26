<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Pancake\Models\CityOrderSummary;

class ShippingAddress extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function cityOrderSummary(): \Illuminate\Database\Eloquent\Relations\HasOne|ShippingAddress
    {
        return $this->hasOne(CityOrderSummary::class, 'district_id', 'district_id');
    }
}
