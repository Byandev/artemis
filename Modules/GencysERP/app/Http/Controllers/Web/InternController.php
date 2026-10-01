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
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\Intern;
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
     * Posted straight at n8n from here. The roster is not one of the batch
     * queue's sync types, so there is no flow to raise it through — this is the
     * page's own button and it reports back on the page it was pressed on.
     */
    public function sync(Workspace $workspace): RedirectResponse
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);

        $webhookUrl = config('services.n8n.gencys_interns_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            return back()->with('error', 'Interns sync is not configured yet. Please contact support.');
        }

        $apiKey = $workspace->apiKeys()->first();

        if (blank($workspace->erp_username) || blank($workspace->erp_password) || ! $apiKey) {
            return back()->with('error', 'This workspace is not connected to the ERP. Add ERP credentials and an API key first.');
        }

        $callbackBase = rtrim(config('services.n8n.callback_base_url') ?: config('app.url'), '/');

        $response = Http::timeout(30)->post($webhookUrl, [
            'workspace_id' => $workspace->id,
            'api_key' => $apiKey->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'webhook_url' => "{$callbackBase}/api/v1/public/gencys/interns",
        ]);

        if (! $response->successful()) {
            return back()->with('error', 'Something went wrong while syncing.'."\n".$response->body());
        }

        return back()->with('success', 'Interns sync started. New records from the ERP will appear here shortly.');
    }
}
