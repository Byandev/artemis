<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PageDailyBudgetRecordController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewPageDailyBudgetRecords->value, $workspace);

        $records = QueryBuilder::for(PageDailyBudgetRecord::where('workspace_id', $workspace->id))
            ->with('page')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereHas('page', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('page_id'),
                AllowedFilter::callback('date_from', function ($query, $value) {
                    $query->where('date', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($query, $value) {
                    $query->where('date', '<=', $value);
                }),
            ])
            ->allowedSorts(['date', 'budget', 'created_at'])
            ->defaultSort('-date')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $pages = Page::ofWorkspace($workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('workspaces/page-daily-budget-records/index', [
            'records' => $records,
            'workspace' => $workspace,
            'pages' => $pages,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::CreatePageDailyBudgetRecords->value, $workspace);

        $validated = $request->validate([
            'page_id' => 'required|exists:pages,id',
            'date' => 'required|date',
            'budget' => 'required|numeric|min:0',
        ]);

        PageDailyBudgetRecord::create([
            'workspace_id' => $workspace->id,
            'page_id' => $validated['page_id'],
            'date' => $validated['date'],
            'budget' => $validated['budget'],
        ]);

        return redirect()
            ->route('workspaces.page-daily-budget-records.index', $workspace->slug)
            ->with('success', 'Record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, PageDailyBudgetRecord $pageDailyBudgetRecord)
    {
        $this->authorize(Permission::EditPageDailyBudgetRecords->value, $workspace);

        if ($pageDailyBudgetRecord->workspace_id !== $workspace->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'page_id' => 'required|exists:pages,id',
            'date' => 'required|date',
            'budget' => 'required|numeric|min:0',
        ]);

        $pageDailyBudgetRecord->update($validated);

        return redirect()
            ->route('workspaces.page-daily-budget-records.index', $workspace->slug)
            ->with('success', 'Record updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, PageDailyBudgetRecord $pageDailyBudgetRecord)
    {
        $this->authorize(Permission::DeletePageDailyBudgetRecords->value, $workspace);

        if ($pageDailyBudgetRecord->workspace_id !== $workspace->id) {
            abort(403, 'Unauthorized action.');
        }

        $pageDailyBudgetRecord->delete();

        return redirect()
            ->route('workspaces.page-daily-budget-records.index', $workspace->slug)
            ->with('success', 'Record deleted successfully.');
    }
}
