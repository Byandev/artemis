<?php

namespace Modules\Botcake\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $guarded = [];

    protected $table = 'botcake_sequences';

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
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
}
