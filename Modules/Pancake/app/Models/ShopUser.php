<?php

namespace Modules\Pancake\Models;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopUser extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $table = 'pancake_shop_users';

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
