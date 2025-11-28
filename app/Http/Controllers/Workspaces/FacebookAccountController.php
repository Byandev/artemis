<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\FacebookAccount;
use App\Models\Workspace;
use Inertia\Inertia;

class FacebookAccountController extends Controller
{
    public function index(Workspace $workspace)
    {
        $facebook_accounts = FacebookAccount::ofWorkspace($workspace)
            ->orderBy('created_at', 'asc')
            ->paginate(1000);


        return Inertia::render('workspaces/facebook-accounts/index', [
            'facebook_accounts' => $facebook_accounts,
            'workspace' => $workspace,
        ]);
    }
}
