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

        // Search filter (search by name and code only)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
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

        // Get unique categories for filter dropdown (exclude null/empty values)
        $categories = Product::ofWorkspace($workspace)
            ->select('category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
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

    public function create(Workspace $workspace)
    {
        $pages = \App\Models\Page::ofWorkspace($workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('workspaces/products/create', [
            'workspace' => $workspace,
            'pages' => $pages,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:products,code',
            'category' => 'required|string|max:255',
            'status' => 'required|in:Scaling,Testing,Failed,Inactive',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'page_ids' => 'nullable|array',
            'page_ids.*' => 'exists:pages,id',
        ]);

        $product = Product::create([
            'workspace_id' => $workspace->id,
            'owner_id' => $request->user()->id,
            'title' => $request->name, // Keep title for backward compatibility
            'name' => $request->name,
            'code' => $request->code,
            'category' => $request->category,
            'status' => $request->status,
            'description' => $request->description,
        ]);

        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')
                ->toMediaCollection('PRODUCT_IMAGE');
        }

        // Sync pages to product
        if ($request->has('page_ids')) {
            \App\Models\Page::whereIn('id', $request->page_ids)
                ->update(['product_id' => $product->id]);
        }

        return redirect()->route('workspaces.products.index', $workspace->slug);
    }

    public function edit(Workspace $workspace, Product $product)
    {
        $pages = \App\Models\Page::ofWorkspace($workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $product->load('pages:id,name');

        return Inertia::render('workspaces/products/edit', [
            'workspace' => $workspace,
            'product' => $product,
            'pages' => $pages,
        ]);
    }

    public function update(Request $request, Workspace $workspace, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:products,code,' . $product->id,
            'category' => 'required|string|max:255',
            'status' => 'required|in:Scaling,Testing,Failed,Inactive',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'page_ids' => 'nullable|array',
            'page_ids.*' => 'exists:pages,id',
        ]);

        $product->update([
            'title' => $request->name, // Keep title for backward compatibility
            'name' => $request->name,
            'code' => $request->code,
            'category' => $request->category,
            // ad_budget_today not updated manually, will be synced from Facebook
            'status' => $request->status,
            'description' => $request->description,
        ]);

        if ($request->hasFile('image')) {
            $product->clearMediaCollection('PRODUCT_IMAGE');
            $product->addMediaFromRequest('image')
                ->toMediaCollection('PRODUCT_IMAGE');
        }

        // First, remove product_id from all pages that were previously connected
        \App\Models\Page::where('product_id', $product->id)
            ->update(['product_id' => null]);

        // Then, assign selected pages to this product
        if ($request->has('page_ids') && !empty($request->page_ids)) {
            \App\Models\Page::whereIn('id', $request->page_ids)
                ->update(['product_id' => $product->id]);
        }

        return redirect()->route('workspaces.products.index', $workspace->slug);
    }

    public function destroy(Workspace $workspace, Product $product)
    {
        $product->delete();

        return redirect()->route('workspaces.products.index', $workspace->slug);
    }
}
