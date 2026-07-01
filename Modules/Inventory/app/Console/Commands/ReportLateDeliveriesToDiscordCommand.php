<?php

namespace Modules\Inventory\Console\Commands;

use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Inventory\Console\Commands\Concerns\FormatsInventoryReports;
use Modules\Inventory\Models\InventoryNotificationSetting;
use Modules\Inventory\Models\PurchasedOrder;

class ReportLateDeliveriesToDiscordCommand extends Command
{
    use FormatsInventoryReports;

    /** PO status that takes an order out of the "awaiting delivery" pipeline. */
    private const STATUS_CANCELLED = 8;

    protected $signature = 'inventory:report-late-deliveries
        {--date= : "As of" date (YYYY-MM-DD, defaults to today).}
        {--force : Send now, ignoring each workspace\'s configured send time.}';

    protected $description = 'Post a Discord board of purchase orders still awaiting delivery as of a given date (overdue + due today), most overdue first.';

    public function handle(DiscordNotifier $discord): int
    {
        $asOf = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : Carbon::today();
        $force = (bool) $this->option('force');
        $nowHHMM = now()->format('H:i');

        // Every PO that should have arrived by now (expected on or before the
        // "as of" date), not cancelled, and not yet fully delivered. Future-dated
        // POs aren't actionable yet, so they're excluded.
        $orders = PurchasedOrder::query()
            ->whereNotNull('expected_delivery_date')
            ->whereDate('expected_delivery_date', '<=', $asOf->toDateString())
            ->where('status', '!=', self::STATUS_CANCELLED)
            ->with(['workspace:id,name', 'items.deliveries', 'items.inventoryItem.product:id,name'])
            ->get()
            ->filter(fn (PurchasedOrder $order) => $order->fulfillment_status !== 'delivered')
            ->filter(fn (PurchasedOrder $order) => $order->workspace !== null)
            ->values();

        if ($orders->isEmpty()) {
            $this->info("No purchase orders awaiting delivery as of {$asOf->toDateString()} — nothing to send.");

            return self::SUCCESS;
        }

        $byWorkspace = $orders->groupBy(fn (PurchasedOrder $order) => $order->workspace->id);

        $sentCount = 0;

        $prettyDate = $asOf->format('l, F j, Y');

        foreach ($byWorkspace as $workspaceId => $workspaceOrders) {
            $workspace = $workspaceOrders->first()->workspace;
            $setting = InventoryNotificationSetting::forWorkspace((int) $workspaceId);

            if (! $setting->awaiting_enabled) {
                continue;
            }

            if (! $force && $setting->awaiting_send_at !== $nowHHMM) {
                continue;
            }

            $webhookUrl = $this->webhookFor($setting->discord_webhook_url);

            if (empty($webhookUrl)) {
                $this->warn("No Discord webhook for workspace {$workspace->name} — skipped.");

                continue;
            }

            $overdue = $workspaceOrders->filter(fn (PurchasedOrder $o) => $o->expected_delivery_date->startOfDay()->lt($asOf))->count();
            $dueToday = $workspaceOrders->count() - $overdue;

            $sent = $discord->send('', [
                'author' => ['name' => $workspace->name],
                'title' => "Orders Not Yet Delivered — {$prettyDate}",
                'description' => "{$workspaceOrders->count()} order(s) waiting: {$overdue} past due, {$dueToday} due today.",
                'color' => 0xE74C3C,
                'fields' => $this->buildFields($workspaceOrders, $asOf),
                'footer' => ['text' => 'Inventory'],
            ], $webhookUrl);

            if ($sent) {
                $sentCount++;
            } else {
                $this->warn("Failed to send awaiting-delivery report for workspace {$workspace->name} — check the webhook URL and logs.");
            }
        }

        $this->info("Awaiting-delivery report as of {$asOf->toDateString()}: sent to {$sentCount} workspace(s).");

        return self::SUCCESS;
    }

    /**
     * One embed field per outstanding PO, most overdue first, tagged with its
     * timeliness. The value lists each line item still owing quantity. Capped to
     * Discord's 25-field / 1024-char limits.
     *
     * @param  Collection<int, PurchasedOrder>  $orders
     * @return array<int, array{name: string, value: string, inline: bool}>
     */
    private function buildFields(Collection $orders, Carbon $asOf): array
    {
        $fields = [];

        $sorted = $orders->sortBy(fn (PurchasedOrder $o) => $o->expected_delivery_date->toDateString());

        foreach ($sorted->take(25) as $order) {
            $expected = $order->expected_delivery_date;
            $expectedDay = $expected->copy()->startOfDay();

            if ($expectedDay->lt($asOf)) {
                $tag = $this->pluralDays((int) $expectedDay->diffInDays($asOf)).' past due';
            } else {
                $tag = 'Due today';
            }

            $pending = $order->items
                ->filter(fn ($item) => $item->balance > 0)
                ->map(function ($item) {
                    $name = $this->inventoryItemName($item->inventoryItem);

                    return "Waiting for {$item->balance} of {$item->count} — {$name}";
                })
                ->implode("\n");

            $value = $pending !== '' ? $pending : 'Nothing received yet';

            $fields[] = [
                'name' => $this->purchaseOrderLabel($order)." — {$tag}",
                'value' => $this->clampFieldValue($value),
                'inline' => false,
            ];
        }

        return $fields;
    }
}
