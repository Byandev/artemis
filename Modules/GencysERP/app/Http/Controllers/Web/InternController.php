<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\GencysSyncBatch;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncFlows\SyncFlowRegistry;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InternController extends Controller
{
    use AuthorizesRequests;

    /** Columns the table may be sorted by. */
    private const SORTABLE = [
        'id',
        'intern_id',
        'full_name',
        'company_name',
        'username',
        'contact_number',
        'email',
        'active',
    ];

    public function __construct(private readonly SyncFlowRegistry $flows) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);

        // Default to active interns only; show_all=1 widens to every status.
        $showAll = $request->boolean('show_all');

        $interns = QueryBuilder::for(
            Intern::query()
                ->where('workspace_id', $workspace->id)
                ->with('user:id,name')
                ->when(! $showAll, fn (Builder $q) => $q->where('active', true))
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function (Builder $q) use ($value) {
                        foreach (['full_name', 'company_name', 'username', 'contact_number', 'email'] as $column) {
                            $q->orWhere($column, 'like', "%{$value}%");
                        }
                    });
                }),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('company_name'),
            ])
            ->allowedSorts(self::SORTABLE)
            ->defaultSort('full_name')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return Inertia::render('workspaces/gencys/interns/index', [
            'workspace' => $workspace,
            'interns' => $interns,
            'assignableUsers' => $this->assignableUsers($workspace),
            'query' => [
                'sort' => $request->input('sort', 'full_name'),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
                'showAll' => $showAll,
            ],
        ]);
    }

    /** Assign (or unassign) the workspace user this intern belongs to. */
    public function assignUser(Request $request, Workspace $workspace, Intern $intern): RedirectResponse
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);

        abort_unless($intern->workspace_id === $workspace->id, 404);

        $data = $request->validate([
            'user_id' => [
                'nullable', 'integer',
                Rule::in($this->assignableUserIds($workspace)),
            ],
        ]);

        $intern->update(['user_id' => $data['user_id'] ?? null]);

        return back();
    }

    /** Workspace users (members + owner) who can be assigned to an intern. */
    private function assignableUsers(Workspace $workspace)
    {
        return User::whereIn('id', $this->assignableUserIds($workspace))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function assignableUserIds(Workspace $workspace)
    {
        return $workspace->users()->pluck('users.id')
            ->push($workspace->owner_id)
            ->filter()
            ->unique();
    }

    /** Update an intern's alternate names (aliases used to resolve ERP cells). */
    public function updateOtherNames(Request $request, Workspace $workspace, Intern $intern): RedirectResponse
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);

        abort_unless($intern->workspace_id === $workspace->id, 404);

        $data = $request->validate([
            'other_names' => ['nullable', 'array'],
            'other_names.*' => ['string', 'max:255'],
        ]);

        $aliases = collect($data['other_names'] ?? [])
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $intern->update(['other_names' => $aliases ?: null]);

        return back();
    }

    /** Toggle an intern's active status. */
    public function toggleActive(Request $request, Workspace $workspace, Intern $intern): RedirectResponse
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);

        abort_unless($intern->workspace_id === $workspace->id, 404);

        $intern->update(['active' => ! $intern->active]);

        return back();
    }

    /**
     * Ask the ERP for a fresh intern roster.
     *
     * Raised as a one-type batch rather than fired at n8n from here: a workspace
     * has a single ERP login, so a scrape started while a batch was running would
     * open a second session behind its back. Queued, it waits its turn like
     * everything else and its outcome is readable on the Sync Batches page
     * instead of vanishing into whatever n8n did with it.
     *
     * The credentials are checked here rather than left to the flow so the
     * operator is told no on the page they pressed the button on — the flow's
     * own guard only shows up as a failed run.
     */
    public function sync(Request $request, Workspace $workspace, BatchRunner $runner): RedirectResponse
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);

        if (blank($this->flows->for(GencysSyncRun::TYPE_INTERNS)->webhookUrl())) {
            return back()->with('error', 'Interns sync is not configured yet. Please contact support.');
        }

        if (blank($workspace->erp_username) || blank($workspace->erp_password) || ! $workspace->apiKeys()->exists()) {
            return back()->with('error', 'This workspace is not connected to the ERP. Add ERP credentials and an API key first.');
        }

        $batch = $runner->queue(
            syncTypes: [GencysSyncRun::TYPE_INTERNS],
            workspaceId: $workspace->id,
            source: GencysSyncBatch::SOURCE_MANUAL,
            createdByUserId: $request->user()->id,
        );

        if (! $batch->wasRecentlyCreated) {
            return back()->with('warning', "An interns sync is already queued as batch #{$batch->id} — nothing new was added.");
        }

        if ($batch->total_runs === 0) {
            return back()->with('error', 'Nothing to sync: this workspace has no ERP credentials or no API key.');
        }

        return back()->with(
            'success',
            "Queued batch #{$batch->id}. New records from the ERP will appear here once it runs.",
        );
    }
}
