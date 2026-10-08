<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\TaskManagement\Http\Requests\IndexSpaceMemberCandidateRequest;
use Modules\TaskManagement\Http\Resources\UserSummaryResource;
use Modules\TaskManagement\Models\Space;

class SpaceMemberCandidateController extends Controller
{
    /**
     * The most matches the picker will show at once.
     */
    private const LIMIT = 10;

    /**
     * Search the workspace members who could be added to this space.
     *
     * Only someone who may already manage the members can look accounts up, and
     * a search term is required, so this stays a way to find a colleague you
     * mean to add rather than a readable directory of every account.
     */
    public function index(Workspace $workspace, IndexSpaceMemberCandidateRequest $request, Space $space): AnonymousResourceCollection
    {
        $term = '%'.addcslashes($request->searchTerm(), '%_\\').'%';

        $candidates = $space->workspaceUsers()
            ->whereKeyNot($space->owner_id)
            ->whereNotIn('id', $space->members()->select('users.id'))
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)
                ->orWhere('email', 'like', $term))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return UserSummaryResource::collection($candidates);
    }
}
