<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GencysERP\Support\RmoUpsellStamper;
use Modules\Pancake\Actions\LinkVerificationCallLogsAction;
use Modules\Pancake\Actions\SyncCustomerAction;
use Modules\Pancake\Actions\SyncOrderItemsAction;
use Modules\Pancake\Actions\SyncParcelTrackingAction;
use Modules\Pancake\Actions\SyncPhoneNumberReportsAction;
use Modules\Pancake\Actions\SyncShippingAddressAction;
use Modules\Pancake\Actions\UpsertOrderAction;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;

class SyncOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly Page $page,
        public readonly array $data,
    ) {}

    public function handle(
        UpsertOrderAction $upsertOrder,
        SyncCustomerAction $syncCustomer,
        SyncOrderItemsAction $syncItems,
        SyncShippingAddressAction $syncAddress,
        SyncParcelTrackingAction $syncTracking,
        SyncPhoneNumberReportsAction $syncPhoneReports,
        LinkVerificationCallLogsAction $linkCallLogs,
        RmoUpsellStamper $upsellStamper,
    ): void {
        $hash = md5(json_encode($this->canonical($this->data)));

        $existing = Order::query()
            ->where('order_number', $this->data['id'])
            ->where('shop_id', $this->data['shop_id'])
            ->where('workspace_id', $this->workspace->id)
            ->first();

        // Pancake handed back exactly what was synced last time, so every write
        // below would land the same values again. Only the steps that depend on
        // other feeds still run: calls that arrived since, and Gencys upsell
        // figures for today's RMO rows.
        if ($existing && $existing->sync_hash === $hash) {
            $linkCallLogs->execute($existing);

            if ($this->workspace->is_gencys_partner) {
                OrderForDelivery::query()
                    ->where('order_id', $existing->id)
                    ->whereDate('delivery_date', today())
                    ->get()
                    ->each(fn (OrderForDelivery $row) => $upsellStamper->stampRow($row));
            }

            return;
        }

        $savedOrder = $upsertOrder->execute($this->workspace, $this->data);

        $syncCustomer->execute($savedOrder, $this->data);
        $syncItems->execute($savedOrder, $this->data);
        $syncAddress->execute($savedOrder, $this->data);
        $syncTracking->execute($savedOrder, $this->data, $this->page, $this->workspace);
        $syncPhoneReports->execute($savedOrder, $this->data);

        // Last, and after the address: it matches on the number written there.
        // Calls sync on their own schedule and are stamped unmatched when the
        // order they were about had not arrived yet, so the order claims them
        // on the way in.
        $linkCallLogs->execute($savedOrder);

        // Stamped last, so a sync that fails part-way runs in full next time.
        // Written on the base query so it leaves updated_at alone.
        Order::whereKey($savedOrder->id)->toBase()->update(['sync_hash' => $hash]);
    }

    /**
     * The payload with its lists sorted, so the fingerprint only moves when the
     * content does. Pancake hands back status_history and items in a different
     * order from one fetch to the next.
     */
    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn ($item) => $this->canonical($item), $value);

        if (array_is_list($value)) {
            usort($value, fn ($a, $b) => json_encode($a) <=> json_encode($b));
        } else {
            ksort($value);
        }

        return $value;
    }
}
