<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use App\Models\WorkspaceChecklist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;

class ChecklistController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $checklists = QueryBuilder::for(WorkspaceChecklist::query()->where('workspace_id', $workspace->id))
            ->allowedSorts(['title', 'target', 'required', 'created_at'])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/checklist/index', [
            'workspace' => $workspace,
            'checklists' => $checklists,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'target' => ['required', 'in:Shop,Page'],
            'required' => ['required', 'boolean'],
        ]);

        WorkspaceChecklist::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'target' => $validated['target'],
            'required' => $validated['required'],
        ]);

        return redirect()->route('workspaces.checklist.index', $workspace)
            ->with('success', 'Checklist item created successfully.');
    }

    public function update(Request $request, Workspace $workspace, WorkspaceChecklist $checklist)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        if ($checklist->workspace_id !== $workspace->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'target' => ['required', 'in:Shop,Page'],
            'required' => ['required', 'boolean'],
        ]);

        $checklist->update([
            'title' => $validated['title'],
            'target' => $validated['target'],
            'required' => $validated['required'],
        ]);

        return redirect()->route('workspaces.checklist.index', $workspace)
            ->with('success', 'Checklist item updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceChecklist $checklist)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        if ($checklist->workspace_id !== $workspace->id) {
            abort(403);
        }

        $checklist->delete();

        return redirect()->route('workspaces.checklist.index', $workspace)
            ->with('success', 'Checklist item deleted successfully.');
    }

    public function view(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $hasPage = Page::query()->where('workspace_id', $workspace->id)->exists();
        $hasShop = Shop::query()->where('workspace_id', $workspace->id)->exists();

        $items = WorkspaceChecklist::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('id')
            ->get()
            ->map(function (WorkspaceChecklist $item) use ($hasPage, $hasShop) {
                $isCompleted = $item->target === 'Page' ? $hasPage : $hasShop;

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'target' => $item->target,
                    'required' => (bool) $item->required,
                    'is_completed' => $isCompleted,
                ];
            })
            ->values();

        $completed = $items->where('is_completed', true)->count();
        $total = $items->count();
        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return Inertia::render('workspaces/checklist/view', [
            'workspace' => $workspace,
            'items' => $items,
            'progress' => [
                'completed' => $completed,
                'total' => $total,
                'percent' => $percent,
            ],
            'signals' => [
                'has_page' => $hasPage,
                'has_shop' => $hasShop,
            ],
        ]);
    }
}
