<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            ->withCount(['adAccounts as ad_accounts_count' => fn ($q) => $q->where('meta_ads_accounts.active_sync', true)]);

        $metaUsers = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'email', 'last_synced_at', 'token_expires_at', 'ad_accounts_count'])
            ->defaultSort('-last_synced_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/integrations/meta-fb-accounts', [
            'workspace' => $workspace,
            'metaUsers' => $metaUsers,
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

        $showAll = $request->boolean('show_all');

        $base = AdAccount::forWorkspace($workspace)
            ->visibleTo($request->user(), $workspace)
            ->with([
                'metaUsers' => function ($q) use ($workspace) {
                    $q->whereHas('workspaces', fn ($w) => $w->where('workspaces.id', $workspace->id))
                        ->select('meta_ads_users.id', 'meta_ads_users.name');
                },
                'owner:id,name',
                // Everyone Meta says can reach the account, admins first.
                'people' => fn ($q) => $q
                    ->orderByRaw("FIELD(role, 'Admin', 'Advertiser', 'Draft', 'Analyst')")
                    ->orderBy('name')
                    ->select('id', 'meta_ads_account_id', 'meta_user_id', 'name', 'role', 'user_type'),
            ])
            ->when(! $showAll, fn ($q) => $q->where('active_sync', true));

        $accounts = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::exact('status', 'account_status'),
                AllowedFilter::exact('currency'),
                AllowedFilter::exact('country_code'),
                AllowedFilter::callback('meta_user', function ($query, $value) {
                    $query->whereHas('metaUsers', fn ($q) => $q->where('meta_ads_users.id', $value));
                }),
                AllowedFilter::exact('owner', 'owner_id'),
            ])
            ->allowedSorts(['name', 'business_name', 'currency', 'country_code', 'account_status', 'last_synced_at'])
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $metaUsers = MetaUser::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspace->id))
            ->orderBy('name')
            ->get(['meta_ads_users.id', 'meta_ads_users.name']);

        // Workspace users (members + owner) who can be assigned as an account owner.
        $ownerIds = $workspace->users()->pluck('users.id')
            ->push($workspace->owner_id)
            ->filter()
            ->unique();
        $owners = User::whereIn('id', $ownerIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('workspaces/integrations/meta-ad-accounts', [
            'workspace' => $workspace,
            'adAccounts' => $accounts,
            'metaUsers' => $metaUsers,
            'owners' => $owners,
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
                'showAll' => $showAll,
            ],
        ]);
    }
}
