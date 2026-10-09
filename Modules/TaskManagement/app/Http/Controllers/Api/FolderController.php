<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Http\Requests\StoreFolderRequest;
use Modules\TaskManagement\Http\Requests\UpdateFolderRequest;
use Modules\TaskManagement\Http\Resources\FolderResource;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class FolderController extends Controller
{
    /**
     * List the folders of a space.
     */
    public function index(Workspace $workspace, Space $space): AnonymousResourceCollection
    {
        Gate::authorize('view', $space);

        return FolderResource::collection(
            $space->folders()->orderBy('position')->orderBy('id')->get()
        );
    }

    /**
     * Create a folder inside a space.
     */
    public function store(Workspace $workspace, StoreFolderRequest $request, Space $space): JsonResponse
    {
        $folder = $space->folders()->create([
            ...$request->safe()->only(['name', 'code', 'description', 'metadata']),
            'position' => $request->integer('position', $this->nextPosition($space)),
        ]);

        return FolderResource::make($folder)
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Show a single folder.
     */
    public function show(Workspace $workspace, Folder $folder): FolderResource
    {
        Gate::authorize('view', $folder);

        return FolderResource::make($folder->load('lists'));
    }

    /**
     * Update a folder.
     */
    public function update(Workspace $workspace, UpdateFolderRequest $request, Folder $folder): FolderResource
    {
        $folder->fill($request->safe()->only(['name', 'code', 'description', 'position', 'metadata']));

        if ($request->has('archived')) {
            $folder->archived_at = $request->boolean('archived') ? now() : null;
        }

        $folder->save();

        return FolderResource::make($folder);
    }

    /**
     * Delete a folder together with its lists and tasks.
     */
    public function destroy(Workspace $workspace, Folder $folder): Response
    {
        Gate::authorize('delete', $folder);

        $folder->delete();

        return response()->noContent();
    }

    /**
     * Get the position that places a new folder after the existing ones.
     */
    private function nextPosition(Space $space): int
    {
        return (int) $space->folders()->max('position') + 1;
    }
}
