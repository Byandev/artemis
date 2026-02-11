<?php

namespace Modules\Botcake\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Botcake\Models\Flow;
use Modules\Botcake\Models\Sequence;
use Modules\Botcake\Models\SequenceMessage;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class SequenceController extends Controller
{
    public function index()
    {
        return QueryBuilder::for(Sequence::class)
            ->addSelect([
                'total_sent' => SequenceMessage::query()
                    ->selectRaw('CAST(COALESCE(SUM(sent), 0) AS UNSIGNED)')
                    ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
            ])
            ->addSelect([
                'total_phone_number' => SequenceMessage::query()
                    ->selectRaw('CAST(COALESCE(SUM(total_phone_number), 0) AS UNSIGNED)')
                    ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
            ])
            ->addSelect([
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
            ])
            ->allowedIncludes('page')
            ->allowedSorts([
                // computed sorts
                AllowedSort::callback('total_sent', function ($query, bool $descending) {
                    $direction = $descending ? 'desc' : 'asc';

                    $query->orderBy(
                        SequenceMessage::query()
                            ->selectRaw('COALESCE(SUM(sent), 0)')
                            ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
                        $direction
                    );
                }),

                AllowedSort::callback('total_phone_number', function ($query, bool $descending) {
                    $direction = $descending ? 'desc' : 'asc';

                    $query->orderBy(
                        SequenceMessage::query()
                            ->selectRaw('COALESCE(SUM(total_phone_number), 0)')
                            ->whereColumn('botcake_sequence_messages.sequence_id', 'botcake_sequences.id'),
                        $direction
                    );
                }),

                AllowedSort::callback('success_rate', function ($query, bool $descending) {
                    $direction = $descending ? 'desc' : 'asc';

                    $query->orderBy(
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
                }),
            ])
            ->paginate();
    }
}
