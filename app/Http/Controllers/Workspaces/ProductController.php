<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Workspace;
use App\Services\PostHogService;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewProducts->value, $workspace);

        $user = $request->user();

        $products = QueryBuilder::for(
            Product::ofWorkspace($workspace)
                ->when(
                    TeamVisibility::shouldScope($user, $workspace),
                    fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo($user, $workspace)),
                )
        )
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
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

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
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
            'categories' => $categories,
        ]);
    }

    public function create(Workspace $workspace)
    {
        $this->authorize(Permission::CreateProducts->value, $workspace);
        $shops = Shop::where('workspace_id', $workspace->id)
            ->visibleTo(auth()->user(), $workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('workspaces/products/create', [
            'workspace' => $workspace,
            'shops' => $shops,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::CreateProducts->value, $workspace);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:products,code,NULL,id,workspace_id,'.$workspace->id,
            'category' => 'required|string|max:255',
            'status' => 'required|in:Scaling,Testing,Failed,Inactive',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
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

        if ($request->filled('shop_ids') && is_array($request->shop_ids) && count($request->shop_ids) > 0) {
            Shop::whereIn('id', $request->shop_ids)
                ->where('workspace_id', $workspace->id)
                ->update(['product_id' => $product->id]);
        }

        (new PostHogService)->capture((string) $request->user()->id, 'product_created', [
            'workspace_id' => $workspace->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'category' => $product->category,
            'status' => $product->status,
        ]);

        return redirect()
            ->route('workspaces.products.index', $workspace->slug)
            ->with('success', 'Product created successfully.');
    }

    public function edit(Workspace $workspace, Product $product)
    {
        $this->authorize(Permission::EditProducts->value, $workspace);
        $shops = Shop::where('workspace_id', $workspace->id)
            ->visibleTo(auth()->user(), $workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $product->load(['shops' => function ($query) {
            $query->select('id', 'name', 'product_id');
        }]);

        return Inertia::render('workspaces/products/edit', [
            'workspace' => $workspace,
            'product' => $product,
            'shops' => $shops,
        ]);
    }

    public function update(Request $request, Workspace $workspace, Product $product)
    {
        $this->authorize(Permission::EditProducts->value, $workspace);

        if ($product->workspace_id !== $workspace->id) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:products,code,'.$product->id.',id,workspace_id,'.$workspace->id,
            'category' => 'required|string|max:255',
            'status' => 'required|in:Scaling,Testing,Failed,Inactive',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
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

        // Remove all existing shop connections for this product
        Shop::where('product_id', $product->id)
            ->where('workspace_id', $workspace->id)
            ->update(['product_id' => null]);

        if ($request->filled('shop_ids') && is_array($request->shop_ids) && count($request->shop_ids) > 0) {
            Shop::whereIn('id', $request->shop_ids)
                ->where('workspace_id', $workspace->id)
                ->update(['product_id' => $product->id]);
        }

        return redirect()
            ->route('workspaces.products.index', $workspace->slug)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Workspace $workspace, Product $product)
    {
        $this->authorize(Permission::DeleteProducts->value, $workspace);

        if ($product->workspace_id !== $workspace->id) {
            abort(403, 'Unauthorized action.');
        }

        $product->delete();

        return redirect()->route('workspaces.products.index', $workspace->slug);
    }
}
