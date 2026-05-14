<?php

namespace Modules\Botcake\Http\Sorts\SequenceMessage;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class SuccessRateSort implements Sort
{
    public function __invoke(
        Builder $query,
        bool $descending,
        string $property
    ): Builder {
        $direction = $descending ? 'desc' : 'asc';

        return $query->orderByRaw(
            'COALESCE(
                CAST(botcake_sequence_messages.total_phone_number AS DECIMAL(20,6)) /
                NULLIF(botcake_sequence_messages.sent, 0),
                0
            ) '.$direction
        );
    }
}
