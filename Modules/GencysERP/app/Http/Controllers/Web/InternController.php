<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
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
        'is_active',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);

        $interns = QueryBuilder::for(
            Intern::query()->where('workspace_id', $workspace->id)->with('user:id,name')
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function (Builder $q) use ($value) {
                        foreach (['full_name', 'company_name', 'username', 'contact_number', 'email'] as $column) {
                            $q->orWhere($column, 'like', "%{$value}%");
                        }
                    });
                }),
                AllowedFilter::exact('company_name'),
                AllowedFilter::exact('user', 'user_id'),
                AllowedFilter::callback('status', function (Builder $query, $value) {
                    $query->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
                }),
            ])
            ->allowedSorts(self::SORTABLE)
            ->defaultSort('full_name')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return Inertia::render('workspaces/gencys/interns/index', [
            'workspace' => $workspace,
            'interns' => $interns,
            'users' => $this->assignableUsers($workspace),
            'query' => [
                'sort' => $request->input('sort', 'full_name'),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /** Link an intern to a workspace user (or clear the link). */
    public function assignUser(Request $request, Workspace $workspace, Intern $intern): JsonResponse
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);
        abort_unless($intern->workspace_id === $workspace->id, 404);

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::in($this->assignableUserIds($workspace))],
        ]);

        $intern->update(['user_id' => $data['user_id'] ?? null]);
        $intern->load('user:id,name');

        return response()->json([
            'user' => $intern->user
                ? ['id' => $intern->user->id, 'name' => $intern->user->name]
                : null,
        ]);
    }

    /** Flip an intern between active and inactive. */
    public function toggleActive(Workspace $workspace, Intern $intern): JsonResponse
    {
        $this->authorize(Permission::ViewGencysInterns->value, $workspace);
        abort_unless($intern->workspace_id === $workspace->id, 404);

        $intern->update(['is_active' => ! $intern->is_active]);

        return response()->json(['is_active' => $intern->is_active]);
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

    /** Workspace users (members + owner) who an intern may be linked to. */
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
}
