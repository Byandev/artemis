<?php

namespace Modules\Inventory\Console\Commands;

use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Inventory\Console\Commands\Concerns\FormatsInventoryReports;
use Modules\Inventory\Models\InventoryNotificationSetting;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;

class ReportLateDeliveriesToDiscordCommand extends Command
{
    use FormatsInventoryReports;

    protected $signature = 'inventory:report-late-deliveries
        {--date= : Delivery date to report (YYYY-MM-DD, defaults to today).}
        {--force : Send now, ignoring each workspace\'s configured send time.}';

    protected $description = 'Post a Discord list of items delivered on a given day that arrived after their purchase order\'s expected delivery date.';

    public function handle(DiscordNotifier $discord): int
    {
        $date = $this->option('date') ?: Carbon::today()->toDateString();
        $force = (bool) $this->option('force');
        $nowHHMM = now()->format('H:i');

        $deliveries = PurchasedOrderItemDelivery::query()
            ->whereDate('delivery_date', $date)
            ->with([
                'item.purchasedOrder.workspace:id,name',
                'item.inventoryItem.product:id,name',
            ])
            ->get()
            ->filter(fn ($delivery) => $delivery->item?->purchasedOrder?->workspace)
            ->filter(function ($delivery) {
                $expected = $delivery->item->purchasedOrder->expected_delivery_date;

                return $expected && $delivery->delivery_date->startOfDay()->gt($expected->startOfDay());
            })
            ->values();

        if ($deliveries->isEmpty()) {
            $this->info("No late deliveries on {$date} — nothing to send.");

            return self::SUCCESS;
        }

        $byWorkspace = $deliveries->groupBy(fn ($d) => $d->item->purchasedOrder->workspace->id);

        $sentCount = 0;

        $prettyDate = Carbon::parse($date)->format('F j, Y');

        foreach ($byWorkspace as $workspaceId => $workspaceDeliveries) {
            $workspace = $workspaceDeliveries->first()->item->purchasedOrder->workspace;
            $setting = InventoryNotificationSetting::forWorkspace((int) $workspaceId);

            if (! $setting->awaiting_enabled) {
                continue;
            }

            if (! $force && $setting->awaiting_send_at !== $nowHHMM) {
                continue;
            }

            $webhookUrl = $this->webhookFor($setting->awaiting_webhook_url);

            if (empty($webhookUrl)) {
                $this->warn("No Discord webhook for workspace {$workspace->name} — skipped.");

                continue;
            }

            $sent = $discord->send('', [
                'title' => "{$prettyDate} Late deliveries",
                'description' => $this->buildBody($workspaceDeliveries),
                'color' => 0xE74C3C,
            ], $webhookUrl);

            if ($sent) {
                $sentCount++;
            } else {
                $this->warn("Failed to send late-delivery report for workspace {$workspace->name} — check the webhook URL and logs.");
            }
        }

        $this->info("Late-delivery report for {$date}: sent to {$sentCount} workspace(s).");

        return self::SUCCESS;
    }

    private function buildBody(Collection $deliveries): string
    {
        $lines = $deliveries->map(function ($delivery) {
            $ref = $this->purchaseOrderRef($delivery->item->purchasedOrder);
            $name = $this->inventoryItemName($delivery->item->inventoryItem);
            $expected = $this->humanDate($delivery->item->purchasedOrder->expected_delivery_date);

            return "{$ref}: {$name} - Expected Delivery day: {$expected}";
        })->implode("\n");

        return $this->clampDescription($lines);
    }
}
