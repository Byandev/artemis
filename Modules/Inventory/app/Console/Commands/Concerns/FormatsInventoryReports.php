<?php

namespace Modules\Inventory\Console\Commands\Concerns;

use Carbon\Carbon;
use Modules\Inventory\Models\PurchasedOrder;

trait FormatsInventoryReports
{
    protected function inventoryWebhookUrl(): ?string
    {
        return config('services.discord.inventory_webhook_url')
            ?: config('services.discord.webhook_url');
    }

    protected function webhookFor(?string $configuredUrl): ?string
    {
        return $configuredUrl ?: $this->inventoryWebhookUrl();
    }

    protected function purchaseOrderLabel(PurchasedOrder $order): string
    {
        $ref = $order->cust_po_no ?: $order->delivery_no ?: $order->control_no;

        return $ref ? "Order {$ref}" : "Order #{$order->id}";
    }

    protected function purchaseOrderRef(PurchasedOrder $order): string
    {
        return $order->cust_po_no ?: $order->delivery_no ?: $order->control_no ?: "#{$order->id}";
    }

    protected function humanDate(?Carbon $date): string
    {
        return $date ? $date->format('F j, Y') : 'no date set';
    }

    protected function pluralDays(int $days): string
    {
        return $days.' '.($days === 1 ? 'day' : 'days');
    }

    protected function inventoryItemName(?object $inventoryItem): string
    {
        if (! $inventoryItem) {
            return 'Unknown item';
        }

        return $inventoryItem->product->name
            ?? $inventoryItem->sku
            ?? 'Unknown item';
    }

    protected function clampContent(string $body): string
    {
        return mb_strlen($body) > 2000 ? mb_substr($body, 0, 1997).'...' : $body;
    }

    protected function clampDescription(string $body): string
    {
        return mb_strlen($body) > 4096 ? mb_substr($body, 0, 4093).'...' : $body;
    }

    protected function clampFieldValue(string $body): string
    {
        return mb_strlen($body) > 1024 ? mb_substr($body, 0, 1021).'...' : $body;
    }

    protected function deliveryTimelinessNote(?Carbon $deliveryDate, ?Carbon $expected): string
    {
        if (! $deliveryDate || ! $expected) {
            return 'No expected date';
        }

        $delivered = $deliveryDate->copy()->startOfDay();
        $due = $expected->copy()->startOfDay();

        if ($delivered->lt($due)) {
            return $this->pluralDays((int) $delivered->diffInDays($due)).' early';
        }

        if ($delivered->gt($due)) {
            return $this->pluralDays((int) $due->diffInDays($delivered)).' late';
        }

        return 'On time';
    }
}
