<?php

namespace Modules\Pancake\Models;

use App\Models\User as SystemUser;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $table = 'pancake_users';

    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'user_id');
    }

    public function shopUsers(): HasMany
    {
        return $this->hasMany(ShopUser::class, 'user_id');
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'pancake_shop_users', 'user_id', 'shop_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'confirmed_by', 'id');
    }

    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'assignee_id', 'fb_id');
    }

    public function assignedOrderForDelivery()
    {
        return $this->hasMany(OrderForDelivery::class, 'assignee_id', 'id');
    }
}
