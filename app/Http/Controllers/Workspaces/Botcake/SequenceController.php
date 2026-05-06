<?php

namespace App\Http\Controllers\Workspaces\Botcake;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Botcake\Http\Sorts\Sequence\SuccessRateSort;
use Modules\Botcake\Http\Sorts\Sequence\TotalPhoneNumberSort;
use Modules\Botcake\Http\Sorts\Sequence\TotalSentSort;
use Modules\Botcake\Models\Sequence;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class SequenceController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $sequences = QueryBuilder::for(
            Sequence::query()
                ->whereHas('page', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->with('page:id,name')
                ->appendTotalSent()
                ->appendTotalPhoneNumber()
                ->appendSuccessRate()
        )
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts([
                'name',
                AllowedSort::custom('total_sent', new TotalSentSort),
                AllowedSort::custom('total_phone_number', new TotalPhoneNumberSort),
                AllowedSort::custom('success_rate', new SuccessRateSort),
            ])
            ->defaultSort('-total_sent')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/botcake/sequences', [
            'workspace' => $workspace,
            'sequences' => $sequences,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
