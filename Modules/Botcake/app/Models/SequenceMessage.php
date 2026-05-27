<?php

namespace Modules\Botcake\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SequenceMessage extends Model
{
    protected $guarded = [];

    protected $table = 'botcake_sequence_messages';

    public $incrementing = false;

    protected $keyType = 'int';

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(SequenceMessageDailyStat::class);
    }

    public function scopeAppendSuccessRate(Builder $query): Builder
    {
        return $query->addSelect(DB::raw('
            COALESCE(
                CAST(botcake_sequence_messages.total_phone_number AS DECIMAL(20,6)) /
                NULLIF(botcake_sequence_messages.sent, 0),
                0
            ) AS success_rate
        '));
    }

    /**
     * Replace the row's sent / total_phone_number / delivery / seen with sums
     * from botcake_sequence_message_daily_stats over [$from, $to] and compute
     * success_rate from those sums.
     */
    public function scopeAppendHistorical(Builder $query, string $from, string $to): Builder
    {
        $sub = fn (string $col) => SequenceMessageDailyStat::query()
            ->selectRaw("CAST(COALESCE(SUM($col), 0) AS UNSIGNED)")
            ->whereColumn('botcake_sequence_message_daily_stats.sequence_message_id', 'botcake_sequence_messages.id')
            ->whereBetween('date', [$from, $to]);

        return $query
            ->select([
                'botcake_sequence_messages.id',
                'botcake_sequence_messages.sequence_id',
                'botcake_sequence_messages.name',
                'botcake_sequence_messages.created_at',
                'botcake_sequence_messages.updated_at',
            ])
            ->addSelect([
                'sent' => $sub('sent'),
                'total_phone_number' => $sub('total_phone_number'),
                'delivery' => $sub('delivery'),
                'seen' => $sub('seen'),
                'success_rate' => SequenceMessageDailyStat::query()
                    ->selectRaw('
                        COALESCE(
                            COALESCE(SUM(total_phone_number), 0) /
                            NULLIF(COALESCE(SUM(sent), 0), 0),
                            0
                        )
                    ')
                    ->whereColumn('botcake_sequence_message_daily_stats.sequence_message_id', 'botcake_sequence_messages.id')
                    ->whereBetween('date', [$from, $to]),
            ]);
    }
}
