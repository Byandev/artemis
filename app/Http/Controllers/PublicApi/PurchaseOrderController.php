<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;

class PurchaseOrderController extends Controller
{
    /** ERP status label (lowercased) => local status code (PurchasedOrder::STATUSES). */
    private const STATUS_MAP = [
        'for approval' => 1,
        'approved' => 2,
        'to pay' => 3,
        'paid' => 4,
        'for purchase' => 5,
        'waiting for delivery' => 6,
        'delivered' => 7,
        'cancelled' => 8,
        'canceled' => 8,
    ];

    /**
     * Receive ERP purchase orders synced back by n8n for a single inventory item.
     *
     * Expected payload (one row per PO, as returned by the ERP):
     * {
     *   "inventory_item_id": 123,            // or "inventory_id"
     *   "purchase_orders": [
     *     {
     *       "controlNo": "CN-TP703",         // unique key per workspace (used to upsert the PO)
     *       "issueDate": "20/04/2026",       // d/m/Y
     *       "deliveryNo": "DN-TP703",
     *       "customerPoNo": "CPO-TP703",
     *       "deliveryFee": 0,
     *       "totalAmount": 518400,
     *       "cogAmount": 518400,             // line cost for this inventory item
     *       "status": "For Approval",
     *       "pickupDate": null,              // d/m/Y, optional -> expected delivery date
     *       "quantity": 0                    // optional; ERP currently omits it
     *     }
     *   ]
     * }
     */
    public function sync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'inventory_item_id' => ['required_without:inventory_id', 'integer'],
            'inventory_id' => ['required_without:inventory_item_id', 'integer'],
            'purchase_orders' => ['present', 'array'],
            'purchase_orders.*.controlNo' => ['required', 'string', 'max:255'],
            'purchase_orders.*.issueDate' => ['required', 'string'],
            'purchase_orders.*.deliveryNo' => ['nullable', 'string', 'max:255'],
            'purchase_orders.*.customerPoNo' => ['nullable', 'string', 'max:255'],
            'purchase_orders.*.deliveryFee' => ['nullable', 'numeric'],
            'purchase_orders.*.totalAmount' => ['nullable', 'numeric'],
            'purchase_orders.*.cogAmount' => ['nullable', 'numeric'],
            'purchase_orders.*.status' => ['nullable', 'string'],
            'purchase_orders.*.pickupDate' => ['nullable', 'string'],
            'purchase_orders.*.quantity' => ['nullable', 'integer'],
        ]);

        $inventoryItemId = $validated['inventory_item_id'] ?? $validated['inventory_id'];

        $item = InventoryItem::where('workspace_id', $workspace->id)
            ->where('id', $inventoryItemId)
            ->firstOrFail();

        $synced = DB::transaction(function () use ($workspace, $item, $validated) {
            $count = 0;

            foreach ($validated['purchase_orders'] as $po) {
                $order = PurchasedOrder::updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'control_no' => $po['controlNo'],
                    ],
                    [
                        'issue_date' => $this->parseDate($po['issueDate']),
                        'delivery_no' => $po['deliveryNo'] ?? null,
                        'cust_po_no' => $po['customerPoNo'] ?? null,
                        'delivery_fee' => $po['deliveryFee'] ?? 0,
                        'total_amount' => $po['totalAmount'] ?? 0,
                        'status' => $this->mapStatus($po['status'] ?? null),
                    ]
                );

                PurchasedOrderItem::updateOrCreate(
                    [
                        'inventory_purchased_order_id' => $order->id,
                        'inventory_item_id' => $item->id,
                    ],
                    [
                        'count' => $po['quantity'] ?? 0,
                        'amount' => 0,
                        'total_amount' => $po['cogAmount'] ?? 0,
                        'expected_delivery_date' => $this->parseDate($po['pickupDate'] ?? null),
                    ]
                );

                $count++;
            }

            return $count;
        });

        return response()->json([
            'data' => [
                'inventory_item_id' => $item->id,
                'purchase_orders_synced' => $synced,
            ],
        ]);
    }

    /** Parse the ERP's d/m/Y date string into Y-m-d, or null. */
    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
    }

    /** Map an ERP status label to a local status code, defaulting to 1 (For Approval). */
    private function mapStatus(?string $status): int
    {
        if (empty($status)) {
            return 1;
        }

        $normalized = Str::lower(trim($status));

        if (isset(self::STATUS_MAP[$normalized])) {
            return self::STATUS_MAP[$normalized];
        }

        if (Str::startsWith($normalized, 'delivered')) {
            return 7;
        }

        if (Str::contains($normalized, 'cancel')) {
            return 8;
        }

        return 1;
    }
}
