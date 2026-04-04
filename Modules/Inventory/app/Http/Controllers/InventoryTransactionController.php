<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Inventory\Models\InventoryTransaction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryTransactionController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (!$request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $inventory = QueryBuilder::for(InventoryTransaction::where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('ref_no', 'like', "%{$value}%");
                }),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->whereDate('date', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->whereDate('date', '<=', $value);
                }),
            ])
            ->allowedSorts(['date', 'ref_no', 'remaining_qty', 'created_at'])
            ->defaultSort('-date')
            ->paginate(10)
            ->withQueryString();

        $users = User::get(['id', 'name']);

        return Inertia::render('workspaces/inventory/inventory_transaction/index', [
            'workspace' => $workspace,
            'inventory' => $inventory,
            'query' => [
                ...$request->only(['sort', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'users' => $users,
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

    public function update(Request $request, Workspace $workspace, InventoryTransaction $inventory)
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

    public function destroy(Workspace $workspace, InventoryTransaction $inventory)
    {
        $inventory->delete();

        return redirect()->back()->with('success', 'Entry permanently deleted.');
    }
}
