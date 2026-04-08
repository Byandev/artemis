<?php

namespace Modules\Pancake\Models;

use App\Models\User as SystemUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $table = 'pancake_users';

    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'user_id');
    }
    public function orders()
    {
        return $this->hasMany(Order::class, 'confirmed_by', 'id');
    }

    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'assignee_id', 'fb_id');
    }
}
