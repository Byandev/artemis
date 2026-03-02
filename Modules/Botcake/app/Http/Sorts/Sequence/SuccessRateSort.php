<?php

namespace Modules\Botcake\Http\Sorts\Sequence;

use Illuminate\Database\Eloquent\Builder;
use Modules\Botcake\Models\SequenceMessage;
use Spatie\QueryBuilder\Sorts\Sort;

class SuccessRateSort implements Sort
{
    public function __invoke(
        Builder $query,
        bool $descending,
        string $property
    ): Builder {
        $direction = $descending ? 'desc' : 'asc';

        return $query->orderBy(
            SequenceMessage::query()
                ->selectRaw('
                        COALESCE(
                            (
                                COALESCE(SUM(total_phone_number), 0) /
                                NULLIF(COALESCE(SUM(sent), 0), 0)
                            ) * 100,
                            0
                        )
                    ')
                ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
            $direction
        );
    }
}
