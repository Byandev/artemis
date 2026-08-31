<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use App\Models\WorkspaceChecklist;
use App\Models\WorkspaceChecklistCompletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ChecklistProgressController extends Controller
{
    use AuthorizesRequests;

    /**
     * Targets whose checklist items cannot be ticked off without evidence.
     */
    private const PROOF_REQUIRED_TARGETS = ['Page'];

    public function index(Request $request, Workspace $workspace, string $target, int $targetId): JsonResponse
    {
        $this->authorizeWorkspaceMembership($request, $workspace);
        $this->authorize(Permission::ViewChecklist->value, $workspace);

        [$targetName, $targetModel, $targetType] = $this->resolveTarget($workspace, $target, $targetId);

        $items = WorkspaceChecklist::query()
            ->where('workspace_id', $workspace->id)
            ->where('target', $targetName)
            ->orderBy('id')
            ->get();

        $completions = WorkspaceChecklistCompletion::query()
            ->with(['checkedBy:id,name', 'media'])
            ->where('workspace_id', $workspace->id)
            ->where('target_type', $targetType)
            ->where('target_id', $targetModel->getKey())
            ->whereIn('workspace_checklist_id', $items->pluck('id'))
            ->get()
            ->keyBy('workspace_checklist_id');

        $proofRequired = $this->requiresProof($targetName);

        $responseItems = $items->map(function (WorkspaceChecklist $item) use ($completions, $proofRequired, $workspace) {
            /** @var WorkspaceChecklistCompletion|null $completion */
            $completion = $completions->get($item->id);
            $proof = $completion?->proof();

            return [
                'id' => $item->id,
                'title' => $item->title,
                'target' => $item->target,
                'required' => (bool) $item->required,
                'requires_proof' => $proofRequired,
                'is_completed' => $completion !== null,
                'checked_by_name' => $completion?->checkedBy?->name,
                'checked_at' => $completion?->checked_at?->toISOString(),
                'note' => $completion?->note,
                'proof' => $proof ? [
                    'file_name' => $proof->file_name,
                    'mime_type' => $proof->mime_type,
                    'size' => $proof->size,
                    'url' => route('workspaces.checklist.progress.proof.show', [
                        'workspace' => $workspace->slug,
                        'completion' => $completion->id,
                    ]),
                ] : null,
            ];
        })->values();

        return response()->json([
            'items' => $responseItems,
            'proof_required' => $proofRequired,
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $target, int $targetId): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWorkspaceMembership($request, $workspace);
        $this->authorize(Permission::EditChecklist->value, $workspace);

        [$targetName, $targetModel, $targetType] = $this->resolveTarget($workspace, $target, $targetId);

        $validated = $request->validate([
            'checklist_id' => ['required', 'integer'],
        ]);

        $checklist = WorkspaceChecklist::query()->findOrFail($validated['checklist_id']);

        if ($checklist->workspace_id !== $workspace->id || $checklist->target !== $targetName) {
            abort(403);
        }

        $completion = WorkspaceChecklistCompletion::query()
            ->where('workspace_id', $workspace->id)
            ->where('workspace_checklist_id', $checklist->id)
            ->where('target_type', $targetType)
            ->where('target_id', $targetModel->getKey())
            ->first();

        // Evidence is demanded the first time the item is ticked off. Once a
        // proof is on file a repeat call stays idempotent — the frontend
        // re-posts on retry, and an already-proven item should not 422.
        $proofRequired = $this->requiresProof($targetName) && ! ($completion && $completion->proof());

        $proofInput = $request->validate([
            'proof' => [$proofRequired ? 'required' : 'nullable', ...$this->proofRules()],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $completion) {
            $completion = WorkspaceChecklistCompletion::query()->create([
                'workspace_id' => $workspace->id,
                'workspace_checklist_id' => $checklist->id,
                'target_type' => $targetType,
                'target_id' => $targetModel->getKey(),
                'checked_by' => $user?->id,
                'checked_at' => now(),
                'note' => $proofInput['note'] ?? null,
            ]);
        } elseif (array_key_exists('note', $proofInput)) {
            $completion->update(['note' => $proofInput['note']]);
        }

        if ($request->hasFile('proof')) {
            // The collection is singleFile(), so this replaces any existing
            // proof and deletes the old object from the bucket.
            $completion->addMediaFromRequest('proof')
                ->toMediaCollection(WorkspaceChecklistCompletion::PROOF_COLLECTION);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Workspace $workspace, string $target, int $targetId): JsonResponse
    {
        $this->authorizeWorkspaceMembership($request, $workspace);
        $this->authorize(Permission::EditChecklist->value, $workspace);

        [$targetName, $targetModel, $targetType] = $this->resolveTarget($workspace, $target, $targetId);

        $validated = $request->validate([
            'checklist_id' => ['required', 'integer'],
        ]);

        $checklist = WorkspaceChecklist::query()->findOrFail($validated['checklist_id']);

        if ($checklist->workspace_id !== $workspace->id || $checklist->target !== $targetName) {
            abort(403);
        }

        // Deleted one model at a time, not with a query-builder delete: media
        // library removes the attached proof from storage on the model's own
        // delete event, which a mass delete would skip and orphan the file.
        WorkspaceChecklistCompletion::query()
            ->where('workspace_id', $workspace->id)
            ->where('workspace_checklist_id', $checklist->id)
            ->where('target_type', $targetType)
            ->where('target_id', $targetModel->getKey())
            ->get()
            ->each->delete();

        return response()->json([], 204);
    }

    /**
     * Serve the proof. The bucket is private, so this either hands out a
     * short-lived signed URL or streams the bytes when the disk cannot sign
     * one (a local disk in development) — the file is never publicly readable.
     */
    public function showProof(Request $request, Workspace $workspace, WorkspaceChecklistCompletion $completion)
    {
        $this->authorizeWorkspaceMembership($request, $workspace);
        $this->authorize(Permission::ViewChecklist->value, $workspace);

        if ($completion->workspace_id !== $workspace->id) {
            abort(403);
        }

        $media = $completion->proof();

        abort_unless($media, 404, 'No proof of completion on file for this checklist item.');

        $disk = Storage::disk($media->disk);

        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $media->getPathRelativeToRoot(),
                Carbon::now()->addMinutes(5),
            ));
        }

        return $disk->download($media->getPathRelativeToRoot(), $media->file_name);
    }

    private function requiresProof(string $targetName): bool
    {
        return in_array($targetName, self::PROOF_REQUIRED_TARGETS, true);
    }

    /**
     * `heif` alongside `heic` because iOS photos are routinely detected as the
     * former. No minimum size: `mimes` already rejects a truncated upload,
     * which sniffs as application/x-empty rather than an image.
     *
     * @return array<int, string>
     */
    private function proofRules(): array
    {
        return ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'];
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
