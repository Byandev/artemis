<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceChecklist;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;

class ChecklistController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::ViewChecklist->value, $workspace);

        $checklists = QueryBuilder::for(WorkspaceChecklist::query()->where('workspace_id', $workspace->id))
            ->allowedSorts(['title', 'target', 'required', 'created_at'])
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/checklist/index', [
            'workspace' => $workspace,
            'checklists' => $checklists,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::CreateChecklist->value, $workspace);

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

        $this->authorize(Permission::EditChecklist->value, $workspace);

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

        $this->authorize(Permission::DeleteChecklist->value, $workspace);

        if ($checklist->workspace_id !== $workspace->id) {
            abort(403);
        }

        $checklist->delete();

        return redirect()->route('workspaces.checklist.index', $workspace)
            ->with('success', 'Checklist item deleted successfully.');
    }
}
