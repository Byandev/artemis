<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Ppw;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PpwController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $ppws = QueryBuilder::for(Ppw::where('workspace_id', $workspace->id))
            ->with(['product'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereHas('product', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('product_id'),

                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->whereDate('transaction_date', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->whereDate('transaction_date', '<=', $value);
                }),
            ])
            ->allowedSorts(['transaction_date', 'count', 'created_at'])
            ->defaultSort('-transaction_date')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/inventory/ppw/index', [
            'ppws' => $ppws,
            'products' => Product::where('workspace_id', $workspace->id)->get(),
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'transaction_date' => 'required|date',
            'count' => 'required|integer|min:0',
        ]);

        Ppw::create([
            'workspace_id' => $workspace->id,
            'product_id' => $request->product_id,
            'transaction_date' => $request->transaction_date,
            'count' => $request->count,
        ]);

        return redirect()->route('workspaces.inventory.ppw.index', $workspace->slug)
            ->with('success', 'PPW record created successfully.');
    }

    public function update(Request $request, Workspace $workspace, Ppw $ppw)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'transaction_date' => 'required|date',
            'count' => 'required|integer|min:0',
        ]);

        $ppw->update([
            'product_id' => $request->product_id,
            'transaction_date' => $request->transaction_date,
            'count' => $request->count,
        ]);

        return redirect()->route('workspaces.inventory.ppw.index', $workspace->slug)
            ->with('success', 'PPW record updated.');
    }

    public function destroy(Workspace $workspace, Ppw $ppw)
    {
        $ppw->delete();
        return redirect()->route('workspaces.inventory.ppw.index', $workspace->slug);
    }
}