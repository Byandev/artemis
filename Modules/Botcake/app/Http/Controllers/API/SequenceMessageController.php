<?php

namespace Modules\Botcake\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Botcake\Http\Sorts\SequenceMessage\SuccessRateSort;
use Modules\Botcake\Models\SequenceMessage;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class SequenceMessageController extends Controller
{
    public function index(Request $request)
    {
        return QueryBuilder::for(SequenceMessage::class)
            ->select('botcake_sequence_messages.*')
            ->allowedIncludes(['sequence', 'sequence.page'])
            ->whereHas('sequence.page', function ($query) use ($request) {
                $query->where('workspace_id', $request->workspace->id);
            })
            ->appendSuccessRate()
            ->allowedSorts([
                'name',
                'sent',
                'total_phone_number',
                AllowedSort::custom('success_rate', new SuccessRateSort),
            ])
            ->paginate();
    }
}
