<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $query = Product::ofWorkspace($workspace)->with('owner');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Status filter (for tabs: Analytics, Products, Testing Products)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $products = $query->paginate(10)->withQueryString();

        // Get unique categories for filter dropdown
        $categories = Product::ofWorkspace($workspace)
            ->select('category')
            ->distinct()
            ->pluck('category');

        return Inertia::render('workspaces/products/index', [
            'products' => $products,
            'workspace' => $workspace,
            'filters' => [
                'search' => $request->search ?? '',
                'category' => $request->category ?? '',
                'status' => $request->status ?? '',
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:products,code',
            'category' => 'required|string|max:255',
            'ad_budget_today' => 'required|numeric|min:0',
            'status' => 'required|in:Scaling,Testing,Failed,Inactive',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $product = Product::create([
            'workspace_id' => $workspace->id,
            'owner_id' => auth()->user()->id,
            'title' => $request->name, // Keep title for backward compatibility
            'name' => $request->name,
            'code' => $request->code,
            'category' => $request->category,
            'ad_budget_today' => $request->ad_budget_today,
            'status' => $request->status,
            'description' => $request->description,
        ]);

        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')
                ->toMediaCollection('PRODUCT_IMAGE');
        }

        return redirect()->route('workspaces.products.index', $workspace->slug);
    }

    public function update(Request $request, Workspace $workspace, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:products,code,' . $product->id,
            'category' => 'required|string|max:255',
            'ad_budget_today' => 'required|numeric|min:0',
            'status' => 'required|in:Scaling,Testing,Failed,Inactive',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $product->update([
            'title' => $request->name, // Keep title for backward compatibility
            'name' => $request->name,
            'code' => $request->code,
            'category' => $request->category,
            'ad_budget_today' => $request->ad_budget_today,
            'status' => $request->status,
            'description' => $request->description,
        ]);

        if ($request->hasFile('image')) {
            $product->clearMediaCollection('PRODUCT_IMAGE');
            $product->addMediaFromRequest('image')
                ->toMediaCollection('PRODUCT_IMAGE');
        }

        return redirect()->route('workspaces.products.index', $workspace->slug);
    }

    public function destroy(Workspace $workspace, Product $product)
    {
        $product->delete();

        return redirect()->route('workspaces.products.index', $workspace->slug);
    }
}
