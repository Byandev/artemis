<?php

namespace Modules\Botcake\Http\Sorts\Sequence;

use Illuminate\Database\Eloquent\Builder;
use Modules\Botcake\Models\SequenceMessage;
use Spatie\QueryBuilder\Sorts\Sort;

class TotalPhoneNumberSort implements Sort
{
    public function __invoke(
        Builder $query,
        bool $descending,
        string $property
    ): Builder {
        $direction = $descending ? 'desc' : 'asc';

        return $query->orderBy(
            SequenceMessage::query()
                ->selectRaw('COALESCE(SUM(total_phone_number), 0)')
                ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
            $direction
        );
    }
}
