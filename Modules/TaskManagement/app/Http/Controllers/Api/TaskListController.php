<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Http\Requests\StoreTaskListRequest;
use Modules\TaskManagement\Http\Requests\UpdateTaskListRequest;
use Modules\TaskManagement\Http\Resources\TaskListResource;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\TaskList;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class TaskListController extends Controller
{
    /**
     * List the lists of a space, optionally narrowed to one folder.
     */
    public function index(Workspace $workspace, Request $request, Space $space): AnonymousResourceCollection
    {
        Gate::authorize('view', $space);

        $lists = $space->lists()
            ->when($request->has('folder_id'), fn ($query) => $query->where('folder_id', $request->input('folder_id')))
            ->withCount('tasks')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return TaskListResource::collection($lists);
    }

    /**
     * Create a list inside a space, either loose or inside a folder.
     */
    public function store(Workspace $workspace, StoreTaskListRequest $request, Space $space): JsonResponse
    {
        $list = new TaskList($request->safe()->only(['name', 'description', 'metadata']));
        $list->space_id = $space->id;
        $list->folder_id = $request->input('folder_id');
        $list->position = $request->integer('position', $this->nextPosition($space));
        $list->save();

        return TaskListResource::make($list)
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Show a single list.
     */
    public function show(Workspace $workspace, TaskList $list): TaskListResource
    {
        Gate::authorize('view', $list);

        return TaskListResource::make($list->loadCount('tasks'));
    }

    /**
     * Update a list, including moving it between folders of the same space.
     */
    public function update(Workspace $workspace, UpdateTaskListRequest $request, TaskList $list): TaskListResource
    {
        $list->fill($request->safe()->only(['name', 'description', 'position', 'metadata']));

        if ($request->has('folder_id')) {
            $list->folder_id = $request->input('folder_id');
        }

        if ($request->has('archived')) {
            $list->archived_at = $request->boolean('archived') ? now() : null;
        }

        $list->save();

        return TaskListResource::make($list);
    }

    /**
     * Delete a list together with its tasks.
     */
    public function destroy(Workspace $workspace, TaskList $list): Response
    {
        Gate::authorize('delete', $list);

        $list->delete();

        return response()->noContent();
    }

    /**
     * Get the position that places a new list after the existing ones.
     */
    private function nextPosition(Space $space): int
    {
        return (int) $space->lists()->max('position') + 1;
    }
}
