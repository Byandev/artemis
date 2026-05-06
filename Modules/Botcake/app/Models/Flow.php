<?php

namespace Modules\Botcake\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flow extends Model
{
    protected $guarded = [];

    protected $table = 'botcake_flows';

    public $incrementing = false;

    protected $keyType = 'int';

    public function dailyStats(): HasMany
    {
        return $this->hasMany(FlowDailyStat::class);
    }

    public function page(): BelongsTo
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
