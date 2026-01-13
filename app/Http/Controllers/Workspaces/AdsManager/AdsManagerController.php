<?php

namespace App\Http\Controllers\Workspaces\AdsManager;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;

class AdsManagerController extends Controller
{
    /**
     * Redirect to campaigns page.
     */
    public function index(Workspace $workspace, Request $request)
    {
        return redirect()->route('workspaces.ads-manager.campaigns', $workspace);
    }

    /**
     * Display the campaigns page.
     */
    public function campaigns(Workspace $workspace, Request $request)
    {
        return inertia('workspaces/ads-manager/campaigns', [
            'workspace' => $workspace,
        ]);
    }

    /**
     * Display the ad sets page.
     */
    public function adSets(Workspace $workspace, Request $request)
    {
        return inertia('workspaces/ads-manager/ad-sets', [
            'workspace' => $workspace,
        ]);
    }

    /**
     * Display the ads page.
     */
    public function ads(Workspace $workspace, Request $request)
    {
        return inertia('workspaces/ads-manager/ads', [
            'workspace' => $workspace,
        ]);
    }
}
