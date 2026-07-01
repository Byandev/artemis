<?php

namespace Modules\Inventory\Console\Commands\Concerns;

use Carbon\Carbon;
use Modules\Inventory\Models\PurchasedOrder;

trait FormatsInventoryReports
{
    /**
     * Target webhook for inventory notifications: the inventory-specific channel
     * if set, otherwise the global Discord webhook.
     */
    protected function inventoryWebhookUrl(): ?string
    {
        return config('services.discord.inventory_webhook_url')
            ?: config('services.discord.webhook_url');
    }

    /**
     * Webhook for a specific workspace: its configured URL if set, otherwise the
     * global env fallback.
     */
    protected function webhookFor(?string $configuredUrl): ?string
    {
        return $configuredUrl ?: $this->inventoryWebhookUrl();
    }

    /**
     * Human-readable label for a purchase order, preferring the reference a buyer
     * recognises (Cust PO / Delivery / Control no.), falling back to the id.
     */
    protected function purchaseOrderLabel(PurchasedOrder $order): string
    {
        $ref = $order->cust_po_no ?: $order->delivery_no ?: $order->control_no;

        return $ref ? "Order {$ref}" : "Order #{$order->id}";
    }

    /** Friendly date, e.g. "July 1, 2026". */
    protected function humanDate(?Carbon $date): string
    {
        return $date ? $date->format('F j, Y') : 'no date set';
    }

    /** "1 day" / "3 days". */
    protected function pluralDays(int $days): string
    {
        return $days.' '.($days === 1 ? 'day' : 'days');
    }

    /** Display name for an inventory item: product name if present, else SKU. */
    protected function inventoryItemName(?object $inventoryItem): string
    {
        if (! $inventoryItem) {
            return 'Unknown item';
        }

        return $inventoryItem->product->name
            ?? $inventoryItem->sku
            ?? 'Unknown item';
    }

    /** Truncate to Discord's 4096-char embed-description limit. */
    protected function clampDescription(string $body): string
    {
        return mb_strlen($body) > 4096 ? mb_substr($body, 0, 4093).'...' : $body;
    }

    /** Truncate to Discord's 1024-char embed field-value limit. */
    protected function clampFieldValue(string $body): string
    {
        return mb_strlen($body) > 1024 ? mb_substr($body, 0, 1021).'...' : $body;
    }

    /**
     * Plain-English note on how a delivery landed against its PO's expected date,
     * e.g. "On time", "3 days late", "2 days early".
     */
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
