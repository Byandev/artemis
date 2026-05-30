<?php

namespace Modules\Pancake\Models;

use App\Models\PancakeUserPosDailyReport;
use App\Models\PancakeUserRmoDailyReport;
use App\Models\Shop;
use App\Models\User as SystemUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function posReports(): HasMany
    {
        return $this->hasMany(PancakeUserPosDailyReport::class, 'pancake_user_id');
    }

    public function rmoReports(): HasMany
    {
        return $this->hasMany(PancakeUserRmoDailyReport::class, 'pancake_user_id');
    }
}
