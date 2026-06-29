<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;

/**
 * Mutation endpoints for the PO monitoring data that now lives inside the
 * purchased-orders page (deliveries, expected dates, status, remarks). The
 * listing itself is served by PurchasedOrderController@index. Delivery
 * timeliness is derived (see PurchasedOrderItem) and intentionally read-only.
 */
class PurchaseOrderMonitoringController extends Controller
{
    use AuthorizesRequests;

    public function storeDelivery(Request $request, Workspace $workspace, PurchasedOrderItem $purchasedOrderItem)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        $this->ensureItemBelongsToWorkspace($purchasedOrderItem, $workspace);

        $purchasedOrderItem->deliveries()->create($this->validateDelivery($request));

        return back()->with('success', 'Delivery recorded.');
    }

    public function updateDelivery(Request $request, Workspace $workspace, PurchasedOrderItemDelivery $delivery)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        $this->ensureItemBelongsToWorkspace($delivery->item, $workspace);

        $delivery->update($this->validateDelivery($request));

        return back()->with('success', 'Delivery updated.');
    }

    public function destroyDelivery(Workspace $workspace, PurchasedOrderItemDelivery $delivery)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        $this->ensureItemBelongsToWorkspace($delivery->item, $workspace);

        $delivery->delete();

        return back()->with('success', 'Delivery deleted.');
    }

    public function updateExpectedDelivery(Request $request, Workspace $workspace, PurchasedOrder $purchasedOrder)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        abort_unless($purchasedOrder->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'expected_delivery_date' => 'nullable|date_format:Y-m-d|date',
        ]);

        $purchasedOrder->update([
            'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
        ]);

        return back()->with('success', 'Expected delivery date updated.');
    }

    public function updateStatus(Request $request, Workspace $workspace, PurchasedOrder $purchasedOrder)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        abort_unless($purchasedOrder->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'integer', Rule::in(array_keys(PurchasedOrder::STATUSES))],
        ]);

        $purchasedOrder->update(['status' => $validated['status']]);

        return back()->with('success', 'Status updated.');
    }

    public function updateRemarks(Request $request, Workspace $workspace, PurchasedOrderItem $purchasedOrderItem)
    {
        $this->authorize('Edit Purchased Orders', $workspace);
        $this->ensureItemBelongsToWorkspace($purchasedOrderItem, $workspace);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchasedOrderItem->update([
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return back()->with('success', 'Remarks updated.');
    }

    private function validateDelivery(Request $request): array
    {
        return $request->validate([
            'delivery_date' => 'required|date_format:Y-m-d|date',
            'delivery_no' => 'nullable|string|max:255',
            'qty' => 'required|integer|min:1',
        ]);
    }

    private function ensureItemBelongsToWorkspace(?PurchasedOrderItem $item, Workspace $workspace): void
    {
        abort_unless($item?->purchasedOrder?->workspace_id === $workspace->id, 404);
    }
}
