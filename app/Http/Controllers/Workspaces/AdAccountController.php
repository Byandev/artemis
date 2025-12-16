<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Sorts\AdAccount\FacebookAccountsSort;
use App\Models\AdAccount;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AdAccountController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        $ad_accounts = QueryBuilder::for(AdAccount::ofWorkspace($workspace))
            ->with('facebook_accounts')
            ->allowedSorts([
                'id',
                'name',
                'currency',
                'country_code',
                'status',
                AllowedSort::custom('facebook_accounts', new FacebookAccountsSort()),
            ])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/ad-accounts/index', [
            'ad_accounts' => $ad_accounts,
            'workspace' => $workspace,
            'query' => $request->only(['sort', 'perPage', 'page']),
        ]);
    }
}
