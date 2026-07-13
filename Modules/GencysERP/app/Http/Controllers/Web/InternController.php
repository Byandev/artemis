<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

        $interns = QueryBuilder::for(
            Intern::query()->where('workspace_id', $workspace->id)
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function (Builder $q) use ($value) {
                        foreach (['full_name', 'company_name', 'username', 'contact_number', 'email'] as $column) {
                            $q->orWhere($column, 'like', "%{$value}%");
                        }
                    });
                }),
                // filter[active]=1 → active only; absent/empty → all statuses.
                AllowedFilter::callback('active', function (Builder $query, $value) {
                    if ($value !== null && $value !== '') {
                        $query->where('active', (bool) $value);
                    }
                }),
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
            'query' => [
                'sort' => $request->input('sort', 'full_name'),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /** Toggle an intern's active status. */
    public function toggleActive(Request $request, Workspace $workspace, Intern $intern): RedirectResponse
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);

        abort_unless($intern->workspace_id === $workspace->id, 404);

        $intern->update(['active' => ! $intern->active]);

        return back();
    }

    /** Fire the n8n webhook that scrapes interns and posts them back to the callback. */
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
