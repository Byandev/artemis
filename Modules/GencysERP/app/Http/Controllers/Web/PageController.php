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
use Modules\GencysERP\Models\Page;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PageController extends Controller
{
    use AuthorizesRequests;

    /** Columns the table may be sorted by. */
    private const SORTABLE = [
        'id',
        'page_id',
        'date_created',
        'name',
        'owner',
        'intern_and_brand',
        'status',
        'platform',
    ];

    /** Columns the search box spans. */
    private const SEARCHABLE = [
        'name',
        'owner',
        'intern_and_brand',
        'status',
        'platform',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewGencysPages->value, $workspace);

        $pages = QueryBuilder::for(
            Page::query()
                ->where('workspace_id', $workspace->id)
                // Only what the table renders — no SELECT *.
                ->select([
                    'id', 'page_id', 'date_created', 'name', 'owner',
                    'intern_and_brand', 'gencys_intern_id', 'status', 'platform',
                    'page_url', 'fb_page_id', 'shop_id', 'pos_token',
                ])
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function (Builder $q) use ($value) {
                        foreach (self::SEARCHABLE as $column) {
                            $q->orWhere($column, 'like', "%{$value}%");
                        }
                    });
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('platform'),
            ])
            ->allowedSorts(self::SORTABLE)
            ->defaultSort('-date_created')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return Inertia::render('workspaces/gencys/pages/index', [
            'workspace' => $workspace,
            'pages' => $pages,
            'statuses' => $this->distinctValues($workspace, 'status'),
            'platforms' => $this->distinctValues($workspace, 'platform'),
            'query' => [
                'sort' => $request->input('sort', '-date_created'),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /** Fire the n8n webhook that scrapes pages and posts them back to the callback. */
    public function sync(Workspace $workspace): RedirectResponse
    {
        $this->authorize(Permission::ViewGencysPages->value, $workspace);

        $webhookUrl = config('services.n8n.gencys_pages_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            return back()->with('error', 'Pages sync is not configured yet. Please contact support.');
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
            'webhook_url' => "{$callbackBase}/api/v1/public/gencys/pages",
        ]);

        if (! $response->successful()) {
            return back()->with('error', 'Something went wrong while syncing.'."\n".$response->body());
        }

        return back()->with('success', 'Pages sync started. New records from the ERP will appear here shortly.');
    }

    /**
     * Distinct non-null values for a filter dropdown. Backed by the
     * (workspace_id, {column}) index, so this is an index-only scan.
     *
     * @return list<string>
     */
    private function distinctValues(Workspace $workspace, string $column): array
    {
        return Page::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }
}
