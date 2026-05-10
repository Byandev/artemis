<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $metaUsers = $workspace->metaUsers()
            ->with(['adAccounts' => function ($q) {
                $q->select('meta_ads_accounts.id', 'name', 'currency', 'country_code', 'account_status', 'business_name', 'last_synced_at');
            }])
            ->select('meta_ads_users.id', 'meta_ads_users.name', 'meta_ads_users.email', 'meta_ads_users.token_expires_at', 'meta_ads_users.last_synced_at')
            ->orderByDesc('meta_ads_workspace_user.created_at')
            ->get();

        return Inertia::render('workspaces/integrations/meta', [
            'workspace' => $workspace,
            'metaUsers' => $metaUsers,
        ]);
    }
}
