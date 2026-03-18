<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $table = 'pancake_users';
}
