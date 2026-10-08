<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Http\Requests\StoreSpaceRequest;
use Modules\TaskManagement\Http\Requests\UpdateSpaceRequest;
use Modules\TaskManagement\Http\Resources\SpaceResource;
use Modules\TaskManagement\Models\Attachment;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\Task;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SpaceController extends Controller
{
    /**
     * List the spaces of this workspace the authenticated user owns or belongs to.
     */
    public function index(Workspace $workspace, Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $spaces = Space::query()
            ->inWorkspace($workspace)
            ->visibleTo($user)
            ->with(['members' => fn ($members) => $members->whereKey($user->id)])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return SpaceResource::collection($spaces);
    }

    /**
     * Create a space owned by the authenticated user, seeded with its default statuses.
     */
    public function store(Workspace $workspace, StoreSpaceRequest $request): JsonResponse
    {
        $space = DB::transaction(function () use ($request, $workspace): Space {
            $space = new Space($request->safe()->only(['name', 'code', 'description', 'color', 'position', 'metadata']));
            $space->workspace_id = $workspace->id;
            $space->owner_id = $request->user()->id;
            $space->save();

            $space->seedDefaultStatuses();

            return $space;
        });

        return SpaceResource::make($space)
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Show a single space.
     */
    public function show(Workspace $workspace, Space $space): SpaceResource
    {
        Gate::authorize('view', $space);

        return SpaceResource::make($space);
    }

    /**
     * Update a space.
     */
    public function update(Workspace $workspace, UpdateSpaceRequest $request, Space $space): SpaceResource
    {
        $space->fill($request->safe()->only(['name', 'code', 'description', 'color', 'position', 'metadata']));

        if ($request->has('archived')) {
            $space->archived_at = $request->boolean('archived') ? now() : null;
        }

        $space->save();

        return SpaceResource::make($space);
    }

    /**
     * Delete a space together with everything inside it.
     */
    public function destroy(Workspace $workspace, Space $space): Response
    {
        Gate::authorize('delete', $space);

        DB::transaction(function () use ($space): void {
            $tasks = Task::query()
                ->whereHas('list', fn (Builder $list) => $list->where('space_id', $space->id));

            /*
             * The delete below is a mass delete, so no Eloquent event fires and
             * the media library never gets to clear the disk. Purge the files
             * first or the bucket keeps objects no row points at any more.
             */
            Attachment::purgeForTasks($tasks);

            $tasks->delete();

            $space->delete();
        });

        return response()->noContent();
    }
}
