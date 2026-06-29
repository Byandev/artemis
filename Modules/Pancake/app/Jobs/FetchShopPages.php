<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class FetchShopPages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Shop $shop) {}

    /**
     * Pull the shop's pages from the Pancake POS API and upsert them. Existing
     * pages keep their owner and status (only the name/shop link is refreshed);
     * newly-discovered pages are created active under the shop's owner.
     */
    public function handle(): void
    {
        if (! $this->shop->pos_token) {
            return;
        }

        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$this->shop->id, [
            'api_key' => $this->shop->pos_token,
        ]);

        if ($response->failed()) {
            return;
        }

        $pages = collect($response->json('shop.pages') ?? []);
        $workspaceId = $this->shop->workspace_id;
        $ownerId = $this->shop->pages()->value('owner_id') ?? $this->shop->workspace?->owner_id;

        foreach ($pages as $pageData) {
            if (! isset($pageData['id'])) {
                continue;
            }

            // A page id is globally unique; skip pages already owned by another workspace.
            $existing = Page::withTrashed()->find($pageData['id']);
            if ($existing && $existing->workspace_id !== $workspaceId) {
                continue;
            }

            if ($existing) {
                $existing->update([
                    'shop_id' => $this->shop->id,
                    'name' => $pageData['name'] ?? $existing->name,
                ]);
            } else {
                Page::create([
                    'id' => $pageData['id'],
                    'workspace_id' => $workspaceId,
                    'shop_id' => $this->shop->id,
                    'owner_id' => $ownerId,
                    'name' => $pageData['name'] ?? 'Page '.$pageData['id'],
                    'status' => 'active',
                ]);
            }
        }
    }
}
