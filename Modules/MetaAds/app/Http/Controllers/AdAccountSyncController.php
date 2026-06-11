<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\MetaAds\Jobs\SyncMetaAdAccounts;
use Modules\MetaAds\Models\User as MetaUser;

class AdAccountSyncController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, MetaUser $metaUser): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless($workspace->metaUsers()->whereKey($metaUser->id)->exists(), 404);

        SyncMetaAdAccounts::dispatch($metaUser)->onQueue('meta-ads');

        return back()->with('success', "Sync queued for {$metaUser->name}");
    }
}
