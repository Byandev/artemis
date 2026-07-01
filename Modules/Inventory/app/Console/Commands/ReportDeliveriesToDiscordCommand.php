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
            // A delivery is meaningless without its parent PO; guard against orphans.
            ->filter(fn ($delivery) => $delivery->item?->purchasedOrder?->workspace)
            ->values();

        if ($deliveries->isEmpty()) {
            $this->info("No deliveries recorded on {$date} — nothing to send.");

            return self::SUCCESS;
        }

        $byWorkspace = $deliveries->groupBy(fn ($d) => $d->item->purchasedOrder->workspace->id);

        $sentCount = 0;

        $prettyDate = Carbon::parse($date)->format('l, F j, Y');

        foreach ($byWorkspace as $workspaceId => $workspaceDeliveries) {
            $workspace = $workspaceDeliveries->first()->item->purchasedOrder->workspace;
            $setting = InventoryNotificationSetting::forWorkspace((int) $workspaceId);

            // Respect the workspace's on/off toggle and scheduled time (unless
            // this is a manual --force run).
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

            $totalQty = $workspaceDeliveries->sum('qty');
            $orderCount = $workspaceDeliveries->groupBy(fn ($d) => $d->item->purchasedOrder->id)->count();

            $sent = $discord->send('', [
                'author' => ['name' => $workspace->name],
                'title' => "Items Received — {$prettyDate}",
                'description' => "{$totalQty} item(s) received from {$orderCount} order(s).",
                'color' => 0x2ECC71,
                'fields' => $this->buildFields($workspaceDeliveries),
                'footer' => ['text' => 'Inventory'],
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

    /**
     * One embed field per purchase order. Each line names the item, the quantity
     * received, and whether it was on time. Capped to Discord's 25-field /
     * 1024-char limits.
     *
     * @param  Collection<int, PurchasedOrderItemDelivery>  $deliveries
     * @return array<int, array{name: string, value: string, inline: bool}>
     */
    private function buildFields(Collection $deliveries): array
    {
        $fields = [];

        foreach ($deliveries->groupBy(fn ($d) => $d->item->purchasedOrder->id)->take(25) as $orderDeliveries) {
            $order = $orderDeliveries->first()->item->purchasedOrder;
            $expected = $order->expected_delivery_date;

            $lines = $orderDeliveries->map(function ($delivery) use ($expected) {
                $name = $this->inventoryItemName($delivery->item->inventoryItem);
                $note = $this->deliveryTimelinessNote($delivery->delivery_date, $expected);

                return "{$delivery->qty} × {$name} ({$note})";
            })->implode("\n");

            $fields[] = [
                'name' => $this->purchaseOrderLabel($order),
                'value' => $this->clampFieldValue($lines),
                'inline' => false,
            ];
        }

        return $fields;
    }
}
