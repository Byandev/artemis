<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use App\Models\WorkspaceChecklist;
use App\Models\WorkspaceChecklistCompletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistProgressController extends Controller
{
    public function index(Request $request, Workspace $workspace, string $target, int $targetId): JsonResponse
    {
        $this->authorizeWorkspaceMembership($request, $workspace);

        [$targetName, $targetModel, $targetType] = $this->resolveTarget($workspace, $target, $targetId);

        $items = WorkspaceChecklist::query()
            ->where('workspace_id', $workspace->id)
            ->where('target', $targetName)
            ->orderBy('id')
            ->get();

        $completions = WorkspaceChecklistCompletion::query()
            ->with('checkedBy:id,name')
            ->where('workspace_id', $workspace->id)
            ->where('target_type', $targetType)
            ->where('target_id', $targetModel->getKey())
            ->whereIn('workspace_checklist_id', $items->pluck('id'))
            ->get()
            ->keyBy('workspace_checklist_id');

        $responseItems = $items->map(function (WorkspaceChecklist $item) use ($completions) {
            /** @var WorkspaceChecklistCompletion|null $completion */
            $completion = $completions->get($item->id);

            return [
                'id' => $item->id,
                'title' => $item->title,
                'target' => $item->target,
                'required' => (bool) $item->required,
                'is_completed' => $completion !== null,
                'checked_by_name' => $completion?->checkedBy?->name,
                'checked_at' => $completion?->checked_at?->toISOString(),
            ];
        })->values();

        return response()->json([
            'items' => $responseItems,
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $target, int $targetId): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWorkspaceMembership($request, $workspace);

        [$targetName, $targetModel, $targetType] = $this->resolveTarget($workspace, $target, $targetId);

        $validated = $request->validate([
            'checklist_id' => ['required', 'integer'],
        ]);

        $checklist = WorkspaceChecklist::query()->findOrFail($validated['checklist_id']);

        if ($checklist->workspace_id !== $workspace->id || $checklist->target !== $targetName) {
            abort(403);
        }

        WorkspaceChecklistCompletion::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'workspace_checklist_id' => $checklist->id,
                'target_type' => $targetType,
                'target_id' => $targetModel->getKey(),
            ],
            [
                'checked_by' => $user?->id,
                'checked_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Workspace $workspace, string $target, int $targetId): JsonResponse
    {
        $this->authorizeWorkspaceMembership($request, $workspace);

        [$targetName, $targetModel, $targetType] = $this->resolveTarget($workspace, $target, $targetId);

        $validated = $request->validate([
            'checklist_id' => ['required', 'integer'],
        ]);

        $checklist = WorkspaceChecklist::query()->findOrFail($validated['checklist_id']);

        if ($checklist->workspace_id !== $workspace->id || $checklist->target !== $targetName) {
            abort(403);
        }

        WorkspaceChecklistCompletion::query()
            ->where('workspace_id', $workspace->id)
            ->where('workspace_checklist_id', $checklist->id)
            ->where('target_type', $targetType)
            ->where('target_id', $targetModel->getKey())
            ->delete();

        return response()->json([], 204);
    }

    private function authorizeWorkspaceMembership(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    /**
     * @return array{0: 'Shop'|'Page', 1: Model, 2: class-string<Model>}
     */
    private function resolveTarget(Workspace $workspace, string $target, int $targetId): array
    {
        $normalized = strtolower($target);

        if ($normalized === 'shop') {
            return ['Shop', $this->findTargetModel(Shop::class, $workspace, $targetId), Shop::class];
        }

        if ($normalized === 'page') {
            return ['Page', $this->findTargetModel(Page::class, $workspace, $targetId), Page::class];
        }

        abort(404);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    private function findTargetModel(string $modelClass, Workspace $workspace, int $targetId): Model
    {
        /** @var Model|null $targetModel */
        $targetModel = $modelClass::query()->find($targetId);

        if (! $targetModel) {
            abort(404);
        }

        if ($targetModel->getAttribute('workspace_id') !== $workspace->id) {
            abort(403);
        }

        return $targetModel;
    }
}
