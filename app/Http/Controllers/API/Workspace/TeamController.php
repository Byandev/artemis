<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Workspace;
use App\Support\TeamScope;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TeamController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $allowedTeams = TeamScope::allowedTeamIds($request->user(), $workspace);

        return QueryBuilder::for(Team::class)
            ->where('workspace_id', $workspace->id)
            ->when($allowedTeams !== null, fn ($q) => $q->whereIn('id', $allowedTeams ?: [-1]))
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
