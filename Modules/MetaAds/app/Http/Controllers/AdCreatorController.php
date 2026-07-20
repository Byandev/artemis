<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;

/**
 * Tags a Meta ad with an internal creator (a workspace member). Meta doesn't
 * expose this, so it's assigned manually. Mirrors AdAccountOwnerController; the
 * routes are gated by "Manage Meta Ads Accounts".
 */
class AdCreatorController extends Controller
{
    /** Single-ad assign / clear. */
    public function update(Request $request, Workspace $workspace, string $ad): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $data = $request->validate([
            'creator_id' => ['nullable', 'integer', Rule::in($this->memberIds($workspace))],
        ]);

        $model = Ad::whereIn('meta_ads_account_id', $this->visibleAccountIds($workspace, $request->user()))
            ->findOrFail($ad);

        $model->update(['creator_id' => $data['creator_id'] ?? null]);

        $model->load('creator:id,name');

        return response()->json([
            'creator' => $model->creator
                ? ['id' => $model->creator->id, 'name' => $model->creator->name]
                : null,
        ]);
    }

    /** Bulk assign / clear a creator across many selected ads. */
    public function bulk(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $data = $request->validate([
            'ad_ids' => ['required', 'array', 'min:1'],
            'ad_ids.*' => ['required', 'string'],
            'creator_id' => ['nullable', 'integer', Rule::in($this->memberIds($workspace))],
        ]);

        $updated = Ad::whereIn('meta_ads_account_id', $this->visibleAccountIds($workspace, $request->user()))
            ->whereIn('id', $data['ad_ids'])
            ->update(['creator_id' => $data['creator_id'] ?? null]);

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    /**
     * Users assignable as a creator: this workspace's members plus its owner —
     * the same allow-list AdAccountOwnerController uses.
     */
    private function memberIds(Workspace $workspace): Collection
    {
        return $workspace->users()->pluck('users.id')
            ->push($workspace->owner_id)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * The ad-account ids the user may act on, so a member can only tag ads in
     * their own visible accounts.
     */
    private function visibleAccountIds(Workspace $workspace, User $user): Collection
    {
        return AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->visibleTo($user, $workspace)
            ->pluck('meta_ads_accounts.id');
    }
}
