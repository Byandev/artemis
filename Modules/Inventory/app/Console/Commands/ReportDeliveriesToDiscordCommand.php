<?php

namespace Modules\Inventory\Console\Commands;

use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Inventory\Console\Commands\Concerns\FormatsInventoryReports;
use Modules\Inventory\Models\InventoryNotificationSetting;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;

class ReportDeliveriesToDiscordCommand extends Command
{
    use FormatsInventoryReports;

    protected $signature = 'inventory:report-deliveries
        {--date= : Delivery date to report (YYYY-MM-DD, defaults to today).}
        {--force : Send now, ignoring each workspace\'s configured send time.}';

    protected $description = 'Post a Discord summary of inventory deliveries recorded on a given day, grouped by workspace and purchase order.';

    public function handle(DiscordNotifier $discord): int
    {
        $date = $this->option('date') ?: Carbon::today()->toDateString();
        $force = (bool) $this->option('force');
        $nowHHMM = now()->format('H:i');

        // Deliveries recorded on the date, with everything needed to group by
        // workspace → purchase order and name each line item.
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

                return $expected && $delivery->delivery_date->startOfDay()->eq($expected->startOfDay());
            })
            ->values();

        if ($deliveries->isEmpty()) {
            $this->info("No on-time deliveries recorded on {$date} — nothing to send.");

            return self::SUCCESS;
        }

        $byWorkspace = $deliveries->groupBy(fn ($d) => $d->item->purchasedOrder->workspace->id);

        $sentCount = 0;

        $prettyDate = Carbon::parse($date)->format('F j, Y');

        foreach ($byWorkspace as $workspaceId => $workspaceDeliveries) {
            $workspace = $workspaceDeliveries->first()->item->purchasedOrder->workspace;
            $setting = InventoryNotificationSetting::forWorkspace((int) $workspaceId);

            if (! $setting->deliveries_enabled) {
                continue;
            }

            if (! $force && $setting->deliveries_send_at !== $nowHHMM) {
                continue;
            }

            $webhookUrl = $this->webhookFor($setting->discord_webhook_url);

            if (empty($webhookUrl)) {
                $this->warn("No Discord webhook for workspace {$workspace->name} — skipped.");

                continue;
            }

            $sent = $discord->send('', [
                'title' => "{$prettyDate} Deliveries",
                'description' => $this->buildBody($workspaceDeliveries),
                'color' => 0x2ECC71,
            ], $webhookUrl);

            if ($sent) {
                $sentCount++;
            } else {
                $this->warn("Failed to send delivery report for workspace {$workspace->name} — check the webhook URL and logs.");
            }
        }

        $this->info("Deliveries report for {$date}: sent to {$sentCount} workspace(s).");

        return self::SUCCESS;
    }

    private function buildBody(Collection $deliveries): string
    {
        $lines = $deliveries->map(function ($delivery) {
            $ref = $this->purchaseOrderRef($delivery->item->purchasedOrder);
            $name = $this->inventoryItemName($delivery->item->inventoryItem);

            return "- {$ref}: {$delivery->qty} {$name}";
        })->implode("\n");

        return $this->clampDescription($lines);
    }
}
