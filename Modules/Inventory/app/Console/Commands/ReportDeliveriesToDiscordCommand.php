<?php

namespace Modules\Inventory\Console\Commands;

use App\Models\Workspace;
use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Inventory\Console\Commands\Concerns\FormatsInventoryReports;
use Modules\Inventory\Models\PurchasedOrder;

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
        // Match on the hour, not the exact minute: schedule:run fires the day's
        // minute-:00 tasks sequentially, so a slow earlier task can push this
        // command past :00 and an H:i match would silently never fire.
        $nowHour = now()->format('H:00');

        $workspaces = $this->workspacesDueNow($force, $nowHour);

        if ($workspaces->isEmpty()) {
            $this->info($force
                ? 'No workspace has the deliveries report enabled with a webhook to post to.'
                : "No workspace is due a deliveries report at {$nowHour}.");

            return self::SUCCESS;
        }

        $ordersByWorkspace = $this->ordersDeliveredOn($date, $workspaces->keys()->all());

        $prettyDate = Carbon::parse($date)->format('F j, Y');
        $sentCount = 0;

        foreach ($workspaces as $workspaceId => $workspace) {
            $orders = $ordersByWorkspace->get($workspaceId, new Collection);

            if ($orders->isEmpty()) {
                $this->line("No deliveries on {$date} for {$workspace->name} — skipped.");

                continue;
            }

            $sent = $discord->send('', [
                'title' => "{$prettyDate} Deliveries",
                'description' => $this->buildBody($orders),
                'color' => 0x2ECC71,
            ], $workspace->inventoryNotificationSetting->deliveries_webhook_url);

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
     * Workspaces that opted into the delivery report: enabled, with their own
     * webhook to post to, and due at the current hour unless --force. Keyed by
     * workspace id. A workspace with no settings row has not opted in.
     *
     * @return Collection<int, Workspace>
     */
    private function workspacesDueNow(bool $force, string $nowHour): Collection
    {
        return Workspace::query()
            ->whereHas('inventoryNotificationSetting', function ($query) use ($force, $nowHour) {
                $query->where('deliveries_enabled', true)
                    ->whereNotNull('deliveries_webhook_url')
                    ->where('deliveries_webhook_url', '!=', '')
                    ->unless($force, fn ($q) => $q->where('deliveries_send_at', $nowHour));
            })
            ->with('inventoryNotificationSetting:id,workspace_id,deliveries_webhook_url')
            ->get(['id', 'name'])
            ->keyBy('id');
    }

    /**
     * Purchase orders carrying at least one delivery recorded on the date, keyed
     * by workspace. Items and deliveries are constrained to the same date so the
     * body only walks the rows it prints. An order is included whether or not it
     * has an expected delivery date.
     *
     * @param  list<int>  $workspaceIds
     * @return Collection<int, Collection<int, PurchasedOrder>>
     */
    private function ordersDeliveredOn(string $date, array $workspaceIds): Collection
    {
        $onDate = fn ($query) => $query->whereDate('delivery_date', $date);

        return PurchasedOrder::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->whereHas('items.deliveries', $onDate)
            ->with([
                'items' => fn ($query) => $query->whereHas('deliveries', $onDate),
                'items.deliveries' => $onDate,
                'items.inventoryItem.product:id,name',
            ])
            ->orderBy('id')
            ->get()
            ->groupBy('workspace_id');
    }

    /**
     * @param  Collection<int, PurchasedOrder>  $orders
     */
    private function buildBody(Collection $orders): string
    {
        $blocks = $orders->map(function (PurchasedOrder $order) {
            $lines = $order->items->flatMap(
                fn ($item) => $item->deliveries->map(function ($delivery) use ($item, $order) {
                    $name = $this->inventoryItemName($item->inventoryItem);
                    $note = $this->deliveryTimelinessNote($delivery->delivery_date, $order->expected_delivery_date);

                    return "- {$delivery->qty} {$name} ({$note})";
                })
            );

            return '**'.$this->purchaseOrderLabel($order)."**\n".$lines->implode("\n");
        });

        return $this->clampDescription($blocks->implode("\n\n"));
    }
}
