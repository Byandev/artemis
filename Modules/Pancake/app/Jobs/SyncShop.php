<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\Intern;

/**
 * Queued equivalent of ShopController@store, used by the Gencys pages sync: pull
 * the shop from the POS API, create it, import its pages, wire it to the intern's
 * teams, and kick off the user/order fetches. There is no acting request here, so
 * the "owner" is the intern's assigned user (falling back to the workspace owner).
 */
class SyncShop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $workspace_id,
        public string $shopId,
        public string $pos_token,
        public ?int $gencys_intern_id,
    ) {}

    public function handle(): void
    {
        $workspace = Workspace::find($this->workspace_id);

        if (! $workspace) {
            return;
        }

        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$this->shopId, [
            'api_key' => $this->pos_token,
        ]);

        if ($response->failed()) {
            return;
        }

        $resJson = $response->json();

        // No acting user in a sync job — the intern's assigned user owns the
        // imported pages, falling back to the workspace owner (both valid users.id).
        $intern = $this->gencys_intern_id ? Intern::find($this->gencys_intern_id) : null;
        $ownerId = $intern?->user_id ?? $workspace->owner_id;

        $shop = Shop::firstOrCreate(
            ['id' => $this->shopId],
            [
                'workspace_id' => $workspace->id,
                'name' => $resJson['shop']['name'] ?? 'Shop '.$this->shopId,
                'avatar_url' => $resJson['shop']['avatar_url'] ?? null,
                'pos_token' => $this->pos_token,
            ]
        );

        $this->syncShopPages($shop, $workspace, $resJson, $ownerId);

        // Auto-attach the shop to every team the intern's user belongs to in this
        // workspace so their teammates can see it without a manual step.
        if ($intern?->user_id) {
            $teamIds = $intern->user->teams()
                ->where('teams.workspace_id', $workspace->id)
                ->pluck('teams.id');

            if ($teamIds->isNotEmpty()) {
                $shop->teams()->syncWithoutDetaching($teamIds);
            }
        }

//        dispatch(new FetchShopUsers($shop))->onQueue('pancake');
//        dispatch(new FetchShopOrders($shop, 1, Carbon::now()->subMonths(2)->unix(), Carbon::now()->unix()))->onQueue('pancake');
    }

    /**
     * Create/refresh the pages that belong to a shop from the POS API response.
     * Existing pages keep their owner and status — only the name/shop link is
     * refreshed; newly-discovered pages get $ownerId and become active.
     */
    private function syncShopPages(Shop $shop, Workspace $workspace, array $resJson, int $ownerId): int
    {
        $pages = collect($resJson['shop']['pages'] ?? []);
        $count = 0;

        foreach ($pages as $pageData) {
            if (! isset($pageData['id'])) {
                continue;
            }

            // A page id is globally unique; skip pages already owned by another workspace.
            $existing = Page::withTrashed()->find($pageData['id']);
            if ($existing && $existing->workspace_id !== $workspace->id) {
                continue;
            }

            if ($existing) {
                $existing->update([
                    'shop_id' => $shop->id,
                    'name' => $pageData['name'] ?? $existing->name,
                ]);
            } else {
                Page::create([
                    'id' => $pageData['id'],
                    'workspace_id' => $workspace->id,
                    'shop_id' => $shop->id,
                    'owner_id' => $ownerId,
                    'name' => $pageData['name'] ?? 'Page '.$pageData['id'],
                    'status' => 'active',
                ]);
            }

            $count++;
        }

        return $count;
    }
}
