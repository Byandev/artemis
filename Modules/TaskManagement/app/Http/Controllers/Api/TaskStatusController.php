<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\TaskManagement\Http\Requests\StoreTaskStatusRequest;
use Modules\TaskManagement\Http\Requests\UpdateTaskStatusRequest;
use Modules\TaskManagement\Http\Resources\TaskStatusResource;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\TaskStatus;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class TaskStatusController extends Controller
{
    /**
     * List the user-defined statuses of a space.
     */
    public function index(Workspace $workspace, Space $space): AnonymousResourceCollection
    {
        Gate::authorize('view', $space);

        return TaskStatusResource::collection(
            $space->statuses()->orderBy('position')->orderBy('id')->get()
        );
    }

    /**
     * Create a status in a space.
     */
    public function store(Workspace $workspace, StoreTaskStatusRequest $request, Space $space): JsonResponse
    {
        $status = DB::transaction(function () use ($request, $space): TaskStatus {
            $status = $space->statuses()->create([
                ...$request->safe()->only(['name', 'type', 'color', 'is_default']),
                'slug' => Str::slug($request->string('name')->value()),
                'position' => $request->integer('position', $this->nextPosition($space)),
            ]);

            if ($status->is_default) {
                $this->clearOtherDefaults($status);
            }

            return $status;
        });

        return TaskStatusResource::make($status)
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Update a status.
     */
    public function update(Workspace $workspace, UpdateTaskStatusRequest $request, TaskStatus $status): TaskStatusResource
    {
        DB::transaction(function () use ($request, $status): void {
            $status->fill($request->safe()->only(['name', 'type', 'color', 'position', 'is_default']));

            if ($request->has('name')) {
                $status->slug = Str::slug($request->string('name')->value());
            }

            $status->save();

            if ($status->is_default) {
                $this->clearOtherDefaults($status);
            }
        });

        return TaskStatusResource::make($status);
    }

    /**
     * Delete a status that no task uses.
     */
    public function destroy(Workspace $workspace, TaskStatus $status): Response|JsonResponse
    {
        Gate::authorize('delete', $status);

        if ($status->tasks()->exists()) {
            return response()->json(
                ['message' => __('This status still has tasks assigned to it.')],
                HttpStatus::HTTP_CONFLICT,
            );
        }

        $status->delete();

        return response()->noContent();
    }

    /**
     * Make sure only one status of the space is marked as the default.
     */
    private function clearOtherDefaults(TaskStatus $status): void
    {
        TaskStatus::query()
            ->where('space_id', $status->space_id)
            ->whereKeyNot($status->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Get the position that places a new status after the existing ones.
     */
    private function nextPosition(Space $space): int
    {
        return (int) $space->statuses()->max('position') + 1;
    }
}
