<?php

namespace Modules\Botcake\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Botcake\Http\Sorts\Sequence\SuccessRateSort;
use Modules\Botcake\Http\Sorts\Sequence\TotalPhoneNumberSort;
use Modules\Botcake\Http\Sorts\Sequence\TotalSentSort;
use Modules\Botcake\Models\Sequence;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class SequenceController extends Controller
{
    public function index(Request $request)
    {
        return QueryBuilder::for(Sequence::class)
            ->allowedIncludes('page')
            ->whereHas('page', function ($query) use ($request) {
                $query->where('workspace_id', $request->workspace->id);
            })
            ->allowedSorts([
                'name',
                AllowedSort::custom('total_sent', new TotalSentSort),
                AllowedSort::custom('total_phone_number', new TotalPhoneNumberSort),
                AllowedSort::custom('success_rate', new SuccessRateSort),
            ])
            ->appendSuccessRate()
            ->appendTotalPhoneNumber()
            ->appendTotalSent()
            ->paginate();
    }
}
