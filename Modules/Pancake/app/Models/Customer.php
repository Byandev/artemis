<?php

namespace Modules\Pancake\Models;

use App\Models\Page;
use App\Models\ParcelJourney;
use App\Models\ShippingAddress;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $table = 'pancake_customers';
}
