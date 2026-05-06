<?php

namespace App\Http\Controllers\Workspaces\Botcake;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Botcake\Models\Flow;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FlowController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $flows = QueryBuilder::for(
            Flow::query()
                ->select('botcake_flows.*')
                ->whereHas('page', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->with('page:id,name')
                ->appendSuccessRate()
        )
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'sent', 'total_phone_number', 'success_rate'])
            ->defaultSort('-sent')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/botcake/flows', [
            'workspace' => $workspace,
            'flows' => $flows,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
