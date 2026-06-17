<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Models\User as MetaUser;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SyncHealthController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $metaUserId = data_get($request->input('filter', []), 'meta_user');

        $accounts = AdAccount::forWorkspace($workspace)
            ->where('active_sync', true)
            ->when($metaUserId, fn ($q) => $q->whereHas('metaUsers', fn ($u) => $u->where('meta_ads_users.id', $metaUserId)))
            ->select('id', 'name', 'business_name', 'last_synced_at')
            ->orderBy('name')
            ->get();

        $accountIds = $accounts->pluck('id')->all();

        // Latest run per (scope_id, entity_type) for these accounts.
        $latestPerAccountEntity = collect();

        if (! empty($accountIds)) {
            $latestPerAccountEntity = DB::table('meta_ads_sync_runs')
                ->whereIn('scope_id', $accountIds)
                ->where('scope_type', AdAccount::class)
                ->whereIn('id', function ($q) use ($accountIds) {
                    $q->from('meta_ads_sync_runs')
                        ->selectRaw('MAX(id)')
                        ->whereIn('scope_id', $accountIds)
                        ->where('scope_type', AdAccount::class)
                        ->groupBy('scope_id', 'entity_type');
                })
                ->get(['scope_id', 'entity_type', 'status', 'records_synced', 'started_at', 'finished_at', 'error_message']);
        }

        $entityTypes = [
            SyncRun::ENTITY_CAMPAIGNS,
            SyncRun::ENTITY_AD_SETS,
            SyncRun::ENTITY_ADS,
            SyncRun::ENTITY_AD_CREATIVES,
            SyncRun::ENTITY_AD_INSIGHTS,
        ];

        // Build the per-account summary as plain arrays so the frontend can
        // render without any mapping logic.
        $byAccount = $latestPerAccountEntity->groupBy('scope_id');

        $summary = [];
        foreach ($accounts as $account) {
            $entries = collect($byAccount[$account->id] ?? [])->keyBy('entity_type');

            $entities = [];
            foreach ($entityTypes as $e) {
                $row = $entries[$e] ?? null;
                $entities[] = [
                    'entity_type' => $e,
                    'status' => $row->status ?? null,
                    'records_synced' => $row->records_synced ?? null,
                    'started_at' => $row->started_at ?? null,
                    'finished_at' => $row->finished_at ?? null,
                    'error_message' => $row->error_message ?? null,
                ];
            }

            $summary[] = [
                'account' => [
                    'id' => (string) $account->id,
                    'name' => $account->name,
                    'business_name' => $account->business_name,
                    'last_synced_at' => $account->last_synced_at,
                ],
                'entities' => $entities,
            ];
        }

        // Recent runs — paginated DataTable.
        $runQuery = SyncRun::query()
            ->where(function ($q) use ($accountIds) {
                $q->where(function ($qq) use ($accountIds) {
                    $qq->whereIn('scope_id', $accountIds)
                        ->where('scope_type', AdAccount::class);
                })->orWhereNull('scope_id'); // workspace-wide runs (e.g. budget snapshots)
            });

        $recent = QueryBuilder::for($runQuery)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('entity_type'),
                AllowedFilter::exact('scope_id'),
                // Account-level filter applied above via $metaUserId; accepted
                // here as a no-op so QueryBuilder doesn't reject the param.
                AllowedFilter::callback('meta_user', fn () => null),
            ])
            ->allowedSorts(['started_at', 'finished_at', 'records_synced', 'entity_type', 'status'])
            ->defaultSort('-started_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $accountNameMap = $accounts->pluck('name', 'id');

        // Tack the account name + duration onto each row so the frontend can
        // render them without re-querying. These are the only computed fields;
        // everything else flows through Eloquent's normal serialization.
        $recent->getCollection()->each(function ($r) use ($accountNameMap) {
            $r->account_name = $r->scope_id ? ($accountNameMap[$r->scope_id] ?? null) : null;
            $r->duration_seconds = $r->started_at && $r->finished_at
                ? $r->started_at->diffInSeconds($r->finished_at)
                : null;
        });

        $failedLast24h = SyncRun::query()
            ->whereIn('status', [SyncRun::STATUS_FAILED, SyncRun::STATUS_RATE_LIMITED])
            ->where('started_at', '>=', now()->subDay())
            ->where(function ($q) use ($accountIds) {
                $q->whereIn('scope_id', $accountIds)
                    ->where('scope_type', AdAccount::class);
            })
            ->count();

        $totalRuns24h = SyncRun::query()
            ->where('started_at', '>=', now()->subDay())
            ->where(function ($q) use ($accountIds) {
                $q->whereIn('scope_id', $accountIds)
                    ->where('scope_type', AdAccount::class);
            })
            ->count();

        $successRuns24h = SyncRun::query()
            ->where('status', SyncRun::STATUS_SUCCESS)
            ->where('started_at', '>=', now()->subDay())
            ->where(function ($q) use ($accountIds) {
                $q->whereIn('scope_id', $accountIds)
                    ->where('scope_type', AdAccount::class);
            })
            ->count();

        $lastSuccessfulRun = SyncRun::query()
            ->where('status', SyncRun::STATUS_SUCCESS)
            ->where(function ($q) use ($accountIds) {
                $q->whereIn('scope_id', $accountIds)
                    ->where('scope_type', AdAccount::class);
            })
            ->latest('started_at')
            ->first(['entity_type', 'scope_id', 'started_at']);

        $metaUsers = MetaUser::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspace->id))
            ->orderBy('name')
            ->get(['meta_ads_users.id', 'meta_ads_users.name']);

        return Inertia::render('workspaces/integrations/meta-health', [
            'workspace' => $workspace,
            'summary' => $summary,
            'metaUsers' => $metaUsers,
            'recent' => $recent,
            'entityTypes' => $entityTypes,
            'failedLast24h' => $failedLast24h,
            'totalRuns24h' => $totalRuns24h,
            'successRuns24h' => $successRuns24h,
            'lastSuccessfulRun' => $lastSuccessfulRun ? [
                'entity_type' => $lastSuccessfulRun->entity_type,
                'account_name' => $lastSuccessfulRun->scope_id
                    ? ($accountNameMap[$lastSuccessfulRun->scope_id] ?? null)
                    : null,
                'started_at' => $lastSuccessfulRun->started_at,
            ] : null,
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
