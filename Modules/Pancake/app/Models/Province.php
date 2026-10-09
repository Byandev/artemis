<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $table = 'pancake_provinces';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
