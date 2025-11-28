<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use App\Models\Workspace;
use Inertia\Inertia;

class AdAccountController extends Controller
{
    public function index(Workspace $workspace)
    {
        $ad_accounts = AdAccount::ofWorkspace($workspace)
            ->with('facebook_accounts')
            ->orderBy('created_at', 'asc')
            ->paginate(1000);

        return Inertia::render('workspaces/ad-accounts/index', [
            'ad_accounts' => $ad_accounts,
            'workspace' => $workspace,
        ]);
    }
}
