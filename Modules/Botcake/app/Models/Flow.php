<?php

namespace Modules\Botcake\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    protected $guarded = [];

    protected $table = 'botcake_flows';

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopeAppendSuccessRate(Builder $query): Builder
    {
        return $query->selectRaw('
                COALESCE(
                    (botcake_flows.total_phone_number / NULLIF(botcake_flows.sent, 0)),
                    0
                ) as success_rate
            ');
    }
}
