<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MetaAds\Models\AdAccount;

class AdAccountOwnerController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, AdAccount $adAccount): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        // Owner must be a user of this workspace (member or the workspace owner).
        $allowedIds = $workspace->users()->pluck('users.id')
            ->push($workspace->owner_id)
            ->filter()
            ->unique();

        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', Rule::in($allowedIds)],
        ]);

        $adAccount->update(['owner_id' => $data['owner_id'] ?? null]);

        $adAccount->load('owner:id,name');

        return response()->json([
            'owner' => $adAccount->owner
                ? ['id' => $adAccount->owner->id, 'name' => $adAccount->owner->name]
                : null,
        ]);
    }
}
