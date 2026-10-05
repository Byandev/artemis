<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Sorts\Checklist\TargetSort;
use App\Http\Sorts\Checklist\TitleNaturalSort;
use App\Models\Workspace;
use App\Models\WorkspaceChecklist;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The checklist page's list, fetched after the page renders so the shell
 * doesn't wait on it. Mutations stay on Workspaces\ChecklistController.
 */
class ChecklistController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::ViewChecklist->value, $workspace);

        return QueryBuilder::for(WorkspaceChecklist::query()->where('workspace_id', $workspace->id))
            ->allowedSorts([
                AllowedSort::custom('title', new TitleNaturalSort),
                AllowedSort::custom('target', new TargetSort),
                'required',
                'created_at',
            ])
            ->paginate(min(max($request->integer('per_page', 10), 1), 100))
            ->withQueryString();
    }
}
