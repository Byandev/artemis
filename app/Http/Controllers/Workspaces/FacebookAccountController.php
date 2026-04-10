<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\FacebookAccount;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FacebookAccountController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        $facebook_accounts = QueryBuilder::for(FacebookAccount::ofWorkspace($workspace))
            ->allowedSorts(['id', 'name', 'created_at'])
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/facebook-accounts/index', [
            'facebook_accounts' => $facebook_accounts,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
