<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\User as MetaUser;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class IntegrationsController extends Controller
{
    /**
     * FB Account page — Facebook users that have authorized this workspace.
     */
    public function fbAccounts(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $base = MetaUser::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspace->id))
            ->withCount('adAccounts');

        $metaUsers = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'email', 'last_synced_at', 'token_expires_at', 'ad_accounts_count'])
            ->defaultSort('-last_synced_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $totalAdAccounts = AdAccount::forWorkspace($workspace)->distinct()->count('meta_ads_accounts.id');

        return Inertia::render('workspaces/integrations/meta-fb-accounts', [
            'workspace' => $workspace,
            'metaUsers' => $metaUsers,
            'totalAdAccounts' => $totalAdAccounts,
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /**
     * Ad Accounts page — every Meta ad account visible to this workspace.
     */
    public function adAccounts(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $base = AdAccount::forWorkspace($workspace)
            ->with(['metaUsers' => function ($q) use ($workspace) {
                $q->whereHas('workspaces', fn ($w) => $w->where('workspaces.id', $workspace->id))
                    ->select('meta_ads_users.id', 'meta_ads_users.name');
            }]);

        $accounts = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::exact('status', 'account_status'),
                AllowedFilter::exact('currency'),
                AllowedFilter::exact('country_code'),
            ])
            ->allowedSorts(['name', 'business_name', 'currency', 'country_code', 'account_status', 'last_synced_at'])
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/integrations/meta-ad-accounts', [
            'workspace' => $workspace,
            'adAccounts' => $accounts,
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
