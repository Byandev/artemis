<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Http\Requests\StoreLabelRequest;
use Modules\TaskManagement\Http\Requests\UpdateLabelRequest;
use Modules\TaskManagement\Http\Resources\LabelResource;
use Modules\TaskManagement\Models\Label;
use Modules\TaskManagement\Models\Space;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class LabelController extends Controller
{
    /**
     * List the labels of a space.
     */
    public function index(Workspace $workspace, Space $space): AnonymousResourceCollection
    {
        Gate::authorize('view', $space);

        return LabelResource::collection($space->labels()->orderBy('name')->get());
    }

    /**
     * Create a label in a space.
     */
    public function store(Workspace $workspace, StoreLabelRequest $request, Space $space): JsonResponse
    {
        $label = $space->labels()->create($request->safe()->only(['name', 'color']));

        return LabelResource::make($label)
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Update a label.
     */
    public function update(Workspace $workspace, UpdateLabelRequest $request, Label $label): LabelResource
    {
        $label->update($request->safe()->only(['name', 'color']));

        return LabelResource::make($label);
    }

    /**
     * Delete a label and detach it from every task.
     */
    public function destroy(Workspace $workspace, Label $label): Response
    {
        Gate::authorize('delete', $label);

        $label->delete();

        return response()->noContent();
    }
}
