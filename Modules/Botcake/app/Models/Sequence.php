<?php

namespace Modules\Botcake\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sequence extends Model
{
    protected $guarded = [];

    protected $table = 'botcake_sequences';

    public $incrementing = false;

    protected $keyType = 'int';

    public function dailyStats(): HasMany
    {
        return $this->hasMany(SequenceDailyStat::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SequenceMessage::class);
    }

    public function scopeAppendTotalSent(Builder $query): Builder
    {
        return $query->addSelect([
            'total_sent' => SequenceMessage::query()
                ->selectRaw('CAST(COALESCE(SUM(sent), 0) AS UNSIGNED)')
                ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
        ]);
    }

    public function scopeAppendTotalPhoneNumber(Builder $query): Builder
    {
        return $query->addSelect([
            'total_phone_number' => SequenceMessage::query()
                ->selectRaw('CAST(COALESCE(SUM(total_phone_number), 0) AS UNSIGNED)')
                ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
        ]);
    }

    public function scopeAppendSuccessRate(Builder $query): Builder
    {
        return $query->addSelect([
            'success_rate' => SequenceMessage::query()
                ->selectRaw('
                COALESCE(
                    (
                        COALESCE(SUM(total_phone_number), 0) /
                        NULLIF(COALESCE(SUM(sent), 0), 0)
                    ),
                    0
                )
            ')
                ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
        ]);
    }

    /**
     * Append total_sent / total_phone_number / success_rate aggregated from
     * botcake_sequence_daily_stats within the [$from, $to] window — the
     * historical equivalent of the appendTotal* + appendSuccessRate trio.
     */
    public function scopeAppendHistorical(Builder $query, string $from, string $to): Builder
    {
        $sub = fn (string $col) => SequenceDailyStat::query()
            ->selectRaw("CAST(COALESCE(SUM($col), 0) AS UNSIGNED)")
            ->whereColumn('botcake_sequence_daily_stats.sequence_id', 'botcake_sequences.id')
            ->whereBetween('date', [$from, $to]);

        return $query->addSelect([
            'total_sent' => $sub('sent'),
            'total_phone_number' => $sub('total_phone_number'),
            'success_rate' => SequenceDailyStat::query()
                ->selectRaw('
                    COALESCE(
                        COALESCE(SUM(total_phone_number), 0) / NULLIF(COALESCE(SUM(sent), 0), 0),
                        0
                    )
                ')
                ->whereColumn('botcake_sequence_daily_stats.sequence_id', 'botcake_sequences.id')
                ->whereBetween('date', [$from, $to]),
        ]);
    }
}
