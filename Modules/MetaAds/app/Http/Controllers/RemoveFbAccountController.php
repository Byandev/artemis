<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\User as MetaUser;

class RemoveFbAccountController extends Controller
{
    /**
     * Remove a Facebook (Meta) account from the workspace.
     *
     * This revokes the account's authorization and deletes the ad accounts it
     * exposed, but intentionally KEEPS the historical campaigns, ad sets, ads,
     * creatives and insights (those tables carry no FK to meta_ads_accounts, so
     * they are left untouched as reporting history).
     */
    public function __invoke(Request $request, Workspace $workspace, MetaUser $metaUser): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless($workspace->metaUsers()->whereKey($metaUser->id)->exists(), 404);

        $name = $metaUser->name;

        DB::transaction(function () use ($workspace, $metaUser) {
            // Revoke this FB account's authorization for the current workspace.
            $workspace->metaUsers()->detach($metaUser->id);

            // If the account still authorizes other workspaces, leave its data intact.
            if ($metaUser->workspaces()->exists()) {
                return;
            }

            // Ad accounts this FB account exposed. Detach the link first, then delete
            // only those no longer reachable through any other FB account. Deleting an
            // AdAccount cascades its team / optimization-rule pivots (FK cascade) but
            // NOT its campaigns, ad sets, ads, creatives or insights — those are kept.
            $accountIds = $metaUser->adAccounts()->pluck('meta_ads_accounts.id');

            $metaUser->adAccounts()->detach();

            AdAccount::whereIn('id', $accountIds)
                ->whereDoesntHave('metaUsers')
                ->delete();

            $metaUser->delete();
        });

        return back()->with('success', "Removed FB account: {$name}");
    }
}
