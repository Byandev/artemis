<?php

namespace Modules\Botcake\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Modules\Botcake\Models\Flow;
use Spatie\QueryBuilder\QueryBuilder;

class FlowController extends Controller
{
    public function index()
    {
        return QueryBuilder::for(Flow::class)
            ->select('*')
            ->appendSuccessRate()
            ->allowedIncludes('page')
            ->allowedSorts(['total_phone_number', 'delivery', 'seen', 'sent', 'success_rate'])
            ->paginate();
    }
}
