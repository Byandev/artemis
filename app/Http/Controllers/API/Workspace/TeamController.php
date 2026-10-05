<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The teams page's list, fetched after the page renders so the shell doesn't
 * wait on it. Mutations stay on Workspaces\TeamController.
 */
class TeamController extends Controller
{
    use AuthorizesRequests;

    /**
     * Scoped users only see the teams they belong to; unrestricted users
     * (owners, ViewAllWorkspaceData) see every team.
     */
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewTeams->value, $workspace);

        return QueryBuilder::for(
            Team::ofWorkspace($workspace)
                ->when(
                    ! TeamVisibility::isUnrestricted($request->user(), $workspace),
                    fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $request->user()->id)),
                )
                ->withCount('members')
                ->with(['members:id,name,email'])
        )
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['id', 'name', 'created_at', 'members_count'])
            ->defaultSort('-created_at')
            ->paginate(min(max($request->integer('per_page', 10), 1), 100))
            ->withQueryString();
    }
}
