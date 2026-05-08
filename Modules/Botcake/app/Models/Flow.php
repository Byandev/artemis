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

    /**
     * Replace the cumulative stat columns with sums from botcake_flow_daily_stats
     * within the [$from, $to] window. The caller is expected to have already
     * limited the SELECT to non-stat Flow columns so the aliases don't collide.
     */
    public function scopeAppendHistorical(Builder $query, string $from, string $to): Builder
    {
        $sub = fn (string $col) => FlowDailyStat::query()
            ->selectRaw("CAST(COALESCE(SUM($col), 0) AS UNSIGNED)")
            ->whereColumn('botcake_flow_daily_stats.flow_id', 'botcake_flows.id')
            ->whereBetween('date', [$from, $to]);

        return $query
            ->addSelect([
                'delivery' => $sub('delivery'),
                'is_clicked' => $sub('is_clicked'),
                'seen' => $sub('seen'),
                'sent' => $sub('sent'),
                'total_phone_number' => $sub('total_phone_number'),
            ])
            ->addSelect([
                'success_rate' => FlowDailyStat::query()
                    ->selectRaw('
                        COALESCE(
                            COALESCE(SUM(total_phone_number), 0) / NULLIF(COALESCE(SUM(sent), 0), 0),
                            0
                        )
                    ')
                    ->whereColumn('botcake_flow_daily_stats.flow_id', 'botcake_flows.id')
                    ->whereBetween('date', [$from, $to]),
            ]);
    }
}
