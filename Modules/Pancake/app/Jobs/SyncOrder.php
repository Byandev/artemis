<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pancake\Actions\LinkVerificationCallLogsAction;
use Modules\Pancake\Actions\SyncCustomerAction;
use Modules\Pancake\Actions\SyncOrderItemsAction;
use Modules\Pancake\Actions\SyncParcelTrackingAction;
use Modules\Pancake\Actions\SyncPhoneNumberReportsAction;
use Modules\Pancake\Actions\SyncShippingAddressAction;
use Modules\Pancake\Actions\UpsertOrderAction;

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
    ): void {
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
    }
}
