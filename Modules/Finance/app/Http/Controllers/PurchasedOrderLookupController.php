<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;

/**
 * The purchase orders the transaction form's delivery-fee picker searches.
 *
 * A "delivery fee of COGS" entry is the freight bill sitting on top of one
 * purchase order, so the fee belongs to that order's products in proportion to
 * how much of each the order carried. Each result therefore ships the order's
 * quantity per product, which is all the form needs to divide the amount.
 */
class PurchasedOrderLookupController extends Controller
{
    use AuthorizesRequests;

    /** Orders returned per search — a picker list, not a browsable index. */
    protected const LIMIT = 20;

    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Read-only lookup feeding the transaction form; gated with the form's
        // own permission rather than the inventory one, so a finance user who
        // never opens the purchased-orders page can still tag an entry.
        $this->authorize(Permission::ViewFinanceTransactions->value, $workspace);

        $search = trim((string) $request->input('search', ''));

        $orders = PurchasedOrder::query()
            ->where('workspace_id', $workspace->id)
            // An order is visible if any of its lines reaches the user's team
            // (a no-op for unrestricted users) — same rule as the PO list.
            ->visibleTo($request->user(), $workspace)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('delivery_no', 'like', "%{$search}%")
                    ->orWhere('cust_po_no', 'like', "%{$search}%")
                    ->orWhere('control_no', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%")
                    ->orWhereHas('items.inventoryItem', fn ($item) => $item->where('sku', 'like', "%{$search}%"));
            }))
            ->with(['items.inventoryItem:id,sku,product_id', 'items.inventoryItem.product:id,name'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'data' => $orders->map(fn (PurchasedOrder $order) => [
                'id' => $order->id,
                'label' => $this->label($order),
                'supplier' => $order->supplier,
                'issue_date' => $order->issue_date?->toDateString(),
                'status_label' => $order->status_label,
                'total_amount' => (float) $order->total_amount,
                'delivery_fee' => (float) $order->delivery_fee,
                'products' => $this->productQuantities($order),
            ])->values(),
        ]);
    }

    /**
     * How the order is named in the picker: its delivery number, falling back
     * through the other references the ERP may be the only one to fill in.
     */
    protected function label(PurchasedOrder $order): string
    {
        return $order->delivery_no
            ?: ($order->cust_po_no ?: ($order->control_no ?: "PO #{$order->id}"));
    }

    /**
     * The order's quantity per product — the weights the delivery fee is split
     * by. Lines are grouped by the catalog product their SKU belongs to, since
     * that name is what a product share is matched on (see
     * TransactionTotals::byProductTag), and several SKUs of one product should
     * carry one combined share rather than a share each.
     *
     * A SKU with no catalog product falls back to its own name, flagged
     * `mapped: false`: leaving it out would quietly hand its slice of the fee
     * to the other products, so it is allocated and called out instead.
     *
     * Zero-quantity lines are dropped — they weigh nothing in the split.
     *
     * @return list<array{product:string, qty:int, mapped:bool}>
     */
    protected function productQuantities(PurchasedOrder $order): array
    {
        return $order->items
            ->map(fn (PurchasedOrderItem $item) => [
                'product' => $item->inventoryItem?->product?->name
                    ?: ($item->inventoryItem?->sku ?: 'Unassigned line'),
                'qty' => (int) $item->count,
                'mapped' => $item->inventoryItem?->product !== null,
            ])
            ->filter(fn (array $row) => $row['qty'] > 0)
            ->groupBy('product')
            ->map(fn ($rows, $product) => [
                'product' => (string) $product,
                'qty' => (int) $rows->sum('qty'),
                'mapped' => (bool) $rows->first()['mapped'],
            ])
            ->sortByDesc('qty')
            ->values()
            ->all();
    }
}
