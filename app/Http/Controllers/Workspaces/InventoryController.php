<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{

    public function index(Request $request, Workspace $workspace)
    {

        $query = Inventory::where('workspace_id', $workspace->id);

        if ($request->filled('search')) {
            $query->where('ref_no', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $inventory = $query->orderBy('date', 'asc')
            ->paginate(10)
            ->withQueryString()
            ->through(function ($item) {
                $item->remaining_qty = ((int) $item->po_qty_in + (int) $item->rts_goods_in)
                    - ((int) $item->po_qty_out + (int) $item->rts_goods_out + (int) $item->rts_bad);
                return $item;
            });

        return Inertia::render('workspaces/inventory/inventory_transaction/index', [
            'workspace' => $workspace,
            'inventory' => $inventory,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'ref_no' => 'nullable|string|max:255',
            'po_qty_in' => 'nullable|integer|min:0',
            'po_qty_out' => 'nullable|integer|min:0',
            'rts_goods_in' => 'nullable|integer|min:0',
            'rts_goods_out' => 'nullable|integer|min:0',
            'rts_bad' => 'nullable|integer|min:0',
        ]);

        $workspace->inventory()->create($validated);

        return redirect()->back()->with('success', 'Entry created successfully.');
    }

    public function update(Request $request, Workspace $workspace, Inventory $inventory)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'ref_no' => 'nullable|string|max:255',
            'po_qty_in' => 'nullable|integer|min:0',
            'po_qty_out' => 'nullable|integer|min:0',
            'rts_goods_in' => 'nullable|integer|min:0',
            'rts_goods_out' => 'nullable|integer|min:0',
            'rts_bad' => 'nullable|integer|min:0',
        ]);

        $inventory->update($validated);

        return redirect()->back()->with('success', 'Entry updated successfully.');
    }

    public function destroy(Workspace $workspace, Inventory $inventory)
    {
        // Standard delete() on a model WITHOUT SoftDeletes trait performs a hard delete
        $inventory->delete();

        return redirect()->back()->with('success', 'Entry permanently deleted.');
    }
}