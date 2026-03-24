<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryPurchasedOrder;
use App\Models\Workspace;
use Illuminate\Http\Request;

class InventoryPurchasedOrderController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'status' => ['nullable', 'integer', 'in:1,2,3,4,5,6,7,8'],
            'q' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = InventoryPurchasedOrder::query()
            ->where('user_id', $request->user()->id);

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['q'])) {
            $q = trim($validated['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('delivery_no', 'like', "%{$q}%")
                    ->orWhere('cust_po_no', 'like', "%{$q}%")
                    ->orWhere('control_no', 'like', "%{$q}%")
                    ->orWhere('item', 'like', "%{$q}%");
            });
        }

        if (! empty($validated['start_date'])) {
            $query->whereDate('issue_date', '>=', $validated['start_date']);
        }

        if (! empty($validated['end_date'])) {
            $query->whereDate('issue_date', '<=', $validated['end_date']);
        }

        $perPage = $validated['per_page'] ?? 20;

        return response()->json(
            $query->orderByDesc('issue_date')->orderByDesc('id')->paginate($perPage)
        );
    }

    public function updateStatus(Request $request, Workspace $workspace, int $order)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:1,2,3,4,5,6,7,8'],
        ]);

        $ownedOrder = InventoryPurchasedOrder::query()
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $ownedOrder->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Status updated.',
            'data' => $ownedOrder->fresh(),
        ]);
    }
}
