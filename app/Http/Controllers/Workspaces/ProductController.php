<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $products = QueryBuilder::for(Product::ofWorkspace($workspace))
            ->with('owner')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('code', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('category'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts([
                'id',
                'name',
                'code',
                'category',
                'status',
                'created_at',
            ])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->withQueryString();

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
            'query' => [
                'filter' => [
                    'search' => $search ?? '',
                    'category' => $request->category ?? '',
                    'status' => $request->status ?? '',
                ],
                'sort' => $sortParam,
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
        // Debug logging
        \Log::info('Product Store Request', [
            'all_data' => $request->all(),
            'page_ids' => $request->page_ids,
            'filled' => $request->filled('page_ids'),
        ]);

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
            'title' => $request->name,
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

        // Assign pages to this product
        if ($request->filled('page_ids') && is_array($request->page_ids) && count($request->page_ids) > 0) {
            \Log::info('Assigning pages to product', ['product_id' => $product->id, 'page_ids' => $request->page_ids]);
            \App\Models\Page::whereIn('id', $request->page_ids)
                ->where('workspace_id', $workspace->id)
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

        // Load pages with the necessary columns (id, name, and product_id for the relationship)
        $product->load(['pages' => function ($query) {
            $query->select('id', 'name', 'product_id');
        }]);

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
            'code' => 'required|string|max:10|unique:products,code,'.$product->id,
            'category' => 'required|string|max:255',
            'status' => 'required|in:Scaling,Testing,Failed,Inactive',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'page_ids' => 'nullable|array',
            'page_ids.*' => 'exists:pages,id',
        ]);

        $product->update([
            'title' => $request->name,
            'name' => $request->name,
            'code' => $request->code,
            'category' => $request->category,
            'status' => $request->status,
            'description' => $request->description,
        ]);

        if ($request->hasFile('image')) {
            $product->clearMediaCollection('PRODUCT_IMAGE');
            $product->addMediaFromRequest('image')
                ->toMediaCollection('PRODUCT_IMAGE');
        }

        // Remove all existing page connections for this product
        \App\Models\Page::where('product_id', $product->id)
            ->where('workspace_id', $workspace->id)
            ->update(['product_id' => null]);

        // Assign new page selections
        if ($request->filled('page_ids') && is_array($request->page_ids) && count($request->page_ids) > 0) {
            \App\Models\Page::whereIn('id', $request->page_ids)
                ->where('workspace_id', $workspace->id)
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
