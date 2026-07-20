<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Exports\PageExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\UpdatePageRequest;
use App\Http\Sorts\Page\OwnerNameSort;
use App\Http\Sorts\Page\ShopNameSort;
use App\Http\Sorts\PendingRequiredChecklistsSort;
use App\Imports\PageImport;
use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use App\Models\Workspace;
use App\Services\Botcake;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class PageController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewPages->value, $workspace);

        $pendingChecklistsSub = DB::table('workspace_checklists as wc')
            ->selectRaw('COUNT(*)')
            ->where('wc.workspace_id', $workspace->id)
            ->where('wc.target', 'Page')
            ->where('wc.required', true)
            ->whereNotExists(function ($sub) use ($workspace) {
                $sub->select(DB::raw(1))
                    ->from('workspace_checklist_completions as wcc')
                    ->whereColumn('wcc.workspace_checklist_id', 'wc.id')
                    ->whereColumn('wcc.target_id', 'pages.id')
                    ->where('wcc.workspace_id', $workspace->id)
                    ->where('wcc.target_type', Page::class);
            });

        $baseQuery = Page::where('pages.workspace_id', $workspace->id)
            ->visibleTo($request->user(), $workspace)
            ->select('pages.*')
            ->selectSub($pendingChecklistsSub, 'pending_required_checklists_count');

        $pages = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::exact('owner_id', 'pages.owner_id'),
            ])
            ->allowedSorts([
                'name',
                'created_at',
                'orders_last_synced_at',
                'deleted_at',
                AllowedSort::custom('shop_name', new ShopNameSort),
                AllowedSort::custom('owner_name', new OwnerNameSort),
                'parcel_journey_enabled',
                AllowedSort::custom('pending_required_checklists_count', new PendingRequiredChecklistsSort),
            ])
            ->with(['shop.teams:id,name', 'owner', 'latestBudget'])
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/pages/index', [
            'pages' => $pages,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'users' => $workspace->users()->get(['users.id', 'users.name']),
        ]);
    }

    public function export(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewPages->value, $workspace);

        $filename = "pages-{$workspace->slug}-".now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new PageExport($workspace, $request->user()), $filename);
    }

    public function import(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::CreatePages->value, $workspace);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:51200'],
        ]);

        $import = new PageImport($workspace, $request->user()->id);

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('workspaces.pages.index', $workspace)
                ->with('error', 'Import failed: '.$e->getMessage());
        }

        return redirect()
            ->route('workspaces.pages.index', $workspace)
            ->with('success', sprintf(
                'Import complete: %d created, %d skipped (already exist), %d failed.',
                $import->created,
                $import->skipped,
                $import->failed,
            ));
    }

    public function edit(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::EditPages->value, $workspace);

        return Inertia::render('workspaces/pages/edit', [
            'workspace' => $workspace,
            'page' => $page,
            'users' => $workspace->users()->get(['users.id', 'users.name']),
        ]);
    }

    public function update(UpdatePageRequest $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::EditPages->value, $workspace);

        $page->update($request->validated());

        return redirect()->route('workspaces.pages.index', $workspace)->with('success', 'Page updated successfully.');
    }

    public function updateBudget(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::EditPageDailyBudgetRecords->value, $workspace);

        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $validated = $request->validate([
            'budget' => ['required', 'numeric', 'min:0'],
        ]);

        PageDailyBudgetRecord::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'page_id' => $page->id,
                'date' => now()->toDateString(),
            ],
            ['budget' => $validated['budget']]
        );

        return redirect()
            ->route('workspaces.pages.index', $workspace)
            ->with('success', 'Page budget updated successfully.');
    }

    public function archive(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::ArchivePages->value, $workspace);

        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $page->deactivate();

        return redirect()->route('workspaces.pages.index', $workspace)->with('success', 'Page archived successfully.');
    }

    public function restore(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::ArchivePages->value, $workspace);

        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $page->activate();

        return redirect()->route('workspaces.pages.index', $workspace);
    }

    public function validatePancakeToken(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'page_id' => 'required|string',
            'token' => 'required|string',
        ]);

        try {
            $response = Http::timeout(10)->get('https://pages.fm/api/public_api/v1/pages/'.$validated['page_id'].'/page_customers', [
                'page_access_token' => $validated['token'],
            ]);

            if ($response->successful() && $response->json()['success']) {
                return response()->json(['valid' => true, 'message' => 'Pancake token is valid.', 'data' => $response->json()], 200);
            }

            return response()->json([
                'valid' => false,
                'message' => 'Invalid Pancake token',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Could not reach Pancake API.',
            ]);
        }
    }

    public function validateFlowId(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'page_id' => 'required|integer',
            'flow_id' => 'required|integer',
            'token' => 'nullable|string',
        ]);

        $page = Page::where('id', $validated['page_id'])
            ->where('workspace_id', $workspace->id)
            ->first();

        if (! $page) {
            return response()->json(['valid' => false, 'message' => 'Page not found in this workspace.']);
        }

        $token = ($validated['token'] ?? null) ?: $page->botcake_token;

        if (blank($token)) {
            return response()->json(['valid' => false, 'message' => 'Enter a Botcake token first.']);
        }

        // Validate by asking Botcake for this specific flow's statistics. The
        // endpoint only succeeds when the flow exists on the page, so a clean
        // response means valid and any failure means it does not.
        try {
            (new Botcake((string) $page->id, $token))->fetchFlowStatistics((string) $validated['flow_id']);
        } catch (ConnectionException $e) {
            return response()->json(['valid' => false, 'message' => 'Could not reach Botcake. Please try again.']);
        } catch (\Throwable $e) {
            $message = str_contains($e->getMessage(), 'invalid_page_id')
                ? "Botcake rejected this page (invalid_page_id). The Botcake access token doesn't match this page's ID — re-copy the access token from Botcake for this exact page."
                : 'Flow ID not found on this page.';

            return response()->json(['valid' => false, 'message' => $message]);
        }

        return response()->json(['valid' => true, 'message' => 'Flow ID is valid.']);
    }

    public function validateCustomFieldId(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'page_id' => 'required|integer',
            'custom_field_id' => 'required|integer',
            'token' => 'nullable|string',
        ]);

        $page = Page::where('id', $validated['page_id'])
            ->where('workspace_id', $workspace->id)
            ->first();

        if (! $page) {
            return response()->json(['valid' => false, 'message' => 'Page not found in this workspace.']);
        }

        $token = $validated['token'] ?: $page->botcake_token;

        if (blank($token)) {
            return response()->json(['valid' => false, 'message' => 'Enter a Botcake token first.']);
        }

        try {
            $fields = (new Botcake((string) $page->id, $token))->fetchCustomFields();
        } catch (\Throwable $e) {
            // Botcake returns "invalid_page_id" when the access token isn't
            // authorized for this page id — point the user at the real fix.
            $message = str_contains($e->getMessage(), 'invalid_page_id')
                ? "Botcake rejected this page (invalid_page_id). The Botcake access token doesn't match this page's ID — re-copy the access token from Botcake for this exact page."
                : 'Botcake: '.$e->getMessage();

            return response()->json(['valid' => false, 'message' => $message]);
        }

        $found = collect($fields)->contains(
            fn ($field) => (int) ($field['id'] ?? 0) === (int) $validated['custom_field_id'],
        );

        return response()->json([
            'valid' => $found,
            'message' => $found
                ? 'Custom field ID is valid.'
                : 'Custom field ID not found on this page.',
        ]);
    }

    public function validateBotcakeToken(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'page_id' => 'required|string',
            'token' => 'required|string',
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeader('access-token', $validated['token'])
                ->get('https://botcake.io/api/public_api/v1/pages/'.$validated['page_id'].'/flows/');

            if ($response->successful()) {
                return response()->json(['valid' => true, 'message' => 'Botcake token is valid.']);
            }

            return response()->json([
                'valid' => false,
                'message' => 'Invalid Botcake token or page ID.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Could not reach Botcake API.',
            ]);
        }
    }
}
