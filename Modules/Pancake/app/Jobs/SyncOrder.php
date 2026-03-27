<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        SyncOrderItemsAction $syncItems,
        SyncShippingAddressAction $syncAddress,
        SyncParcelTrackingAction $syncTracking,
        SyncPhoneNumberReportsAction $syncPhoneReports,
    ): void {
        $savedOrder = $upsertOrder->execute($this->workspace, $this->data);

        $syncItems->execute($savedOrder, $this->data);
        $syncAddress->execute($savedOrder, $this->data);
        $syncTracking->execute($savedOrder, $this->data, $this->page, $this->workspace);
        $syncPhoneReports->execute($savedOrder, $this->data);
    }
}
