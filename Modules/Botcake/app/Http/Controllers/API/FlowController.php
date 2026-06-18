<?php

namespace Modules\Botcake\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\TeamVisibility;
use Illuminate\Http\Request;
use Modules\Botcake\Models\Flow;
use Spatie\QueryBuilder\QueryBuilder;

class FlowController extends Controller
{
    public function index(Request $request)
    {
        return QueryBuilder::for(
            Flow::query()->when(
                TeamVisibility::shouldScope($request->user(), $request->workspace),
                fn ($q) => $q->whereHas('page', fn ($p) => $p->visibleTo($request->user(), $request->workspace)),
            )
        )
            ->select('*')
            ->whereHas('page', function ($query) use ($request) {
                $query->where('workspace_id', $request->workspace->id);
            })
            ->appendSuccessRate()
            ->allowedIncludes('page')
            ->allowedSorts(['total_phone_number', 'delivery', 'seen', 'sent', 'success_rate'])
            ->paginate();
    }
}
