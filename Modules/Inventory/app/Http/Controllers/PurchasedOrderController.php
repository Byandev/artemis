<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;

class PurchasedOrderController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $orders = PurchasedOrder::where('workspace_id', $workspace->id)
            ->with(['items.inventoryItem.product'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('workspaces/inventory/purchased-orders/index', [
            'workspace' => $workspace,
            'orders' => $orders,
        ]);
    }

    public function create(Workspace $workspace)
    {
        return Inertia::render('workspaces/inventory/purchased-orders/create', [
            'workspace' => $workspace,
            'items' => InventoryItem::where('workspace_id', $workspace->id)->with('product')->get(),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'issue_date'    => 'required|date',
            'delivery_no'   => 'nullable|string|max:255',
            'cust_po_no'    => 'nullable|string|max:255',
            'control_no'    => 'nullable|string|max:255',
            'delivery_fee'  => 'required|numeric|min:0',
            'total_amount'  => 'required|numeric|min:0',
            'items'         => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.count'             => 'required|integer|min:1',
            'items.*.amount'            => 'required|numeric|min:0',
            'items.*.total_amount'      => 'required|numeric|min:0',
        ]);

        $order = PurchasedOrder::create([
            'workspace_id' => $workspace->id,
            'issue_date'   => $request->issue_date,
            'delivery_no'  => $request->delivery_no,
            'cust_po_no'   => $request->cust_po_no,
            'control_no'   => $request->control_no,
            'delivery_fee' => $request->delivery_fee,
            'total_amount' => $request->total_amount,
        ]);

        foreach ($request->items as $item) {
            $order->items()->create($item);
        }

        return redirect()->route('workspaces.inventory.purchased-orders.index', $workspace->slug)
            ->with('success', 'Purchased order created successfully.');
    }

    public function edit(Workspace $workspace, PurchasedOrder $purchasedOrder)
    {
        return Inertia::render('workspaces/inventory/purchased-orders/edit', [
            'workspace' => $workspace,
            'order' => $purchasedOrder->load('items.inventoryItem.product'),
            'items' => InventoryItem::where('workspace_id', $workspace->id)->with('product')->get(),
        ]);
    }

    public function update(Request $request, Workspace $workspace, PurchasedOrder $purchasedOrder)
    {
        $request->validate([
            'issue_date'    => 'required|date',
            'delivery_no'   => 'nullable|string|max:255',
            'cust_po_no'    => 'nullable|string|max:255',
            'control_no'    => 'nullable|string|max:255',
            'delivery_fee'  => 'required|numeric|min:0',
            'total_amount'  => 'required|numeric|min:0',
            'items'         => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.count'             => 'required|integer|min:1',
            'items.*.amount'            => 'required|numeric|min:0',
            'items.*.total_amount'      => 'required|numeric|min:0',
        ]);

        $purchasedOrder->update([
            'issue_date'   => $request->issue_date,
            'delivery_no'  => $request->delivery_no,
            'cust_po_no'   => $request->cust_po_no,
            'control_no'   => $request->control_no,
            'delivery_fee' => $request->delivery_fee,
            'total_amount' => $request->total_amount,
        ]);

        $purchasedOrder->items()->delete();
        foreach ($request->items as $item) {
            $purchasedOrder->items()->create($item);
        }

        return redirect()->route('workspaces.inventory.purchased-orders.index', $workspace->slug)
            ->with('success', 'Purchased order updated successfully.');
    }

    public function destroy(Workspace $workspace, PurchasedOrder $purchasedOrder)
    {
        $purchasedOrder->delete();

        return redirect()->route('workspaces.inventory.purchased-orders.index', $workspace->slug)
            ->with('success', 'Purchased order deleted.');
    }
}
