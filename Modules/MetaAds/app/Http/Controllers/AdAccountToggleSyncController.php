<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MetaAds\Models\AdAccount;

class AdAccountToggleSyncController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, AdAccount $adAccount): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $adAccount->update(['active_sync' => ! $adAccount->active_sync]);

        return response()->json(['active_sync' => $adAccount->active_sync]);
    }
}
