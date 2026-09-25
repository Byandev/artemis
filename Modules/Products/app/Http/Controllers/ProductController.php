<?php

namespace Modules\Products\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Workspace;
use App\Services\PostHogService;
use App\Support\TeamVisibility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductForm;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewProducts->value, $workspace);

        $user = $request->user();

        $scoped = fn () => Product::ofWorkspace($workspace)
            ->when(
                TeamVisibility::shouldScope($user, $workspace),
                fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo($user, $workspace)),
            );

        $products = QueryBuilder::for($scoped())
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

        // Headline counts and the per-status tab counts. Deliberately ignores the
        // status filter — the tabs have to keep showing every stage's total while
        // one of them is selected — but honours search/category so the numbers
        // describe the same slice the table is paginating through.
        $filters = (array) $request->input('filter', []);
        $search = $filters['search'] ?? null;
        $category = $filters['category'] ?? null;

        $summaryQuery = $scoped()
            ->when($search, fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$value}%")
                ->orWhere('code', 'like', "%{$value}%")))
            ->when($category, fn ($q, $value) => $q->where('category', $value));

        $countsByStatus = (clone $summaryQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = collect(Product::STATUSES)
            ->mapWithKeys(fn ($status) => [$status => (int) ($countsByStatus[$status] ?? 0)]);

        return Inertia::render('workspaces/products/index', [
            'products' => $products,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
            'categories' => $categories,
            'statusCounts' => $statusCounts,
            'summary' => [
                'total_product_count' => (int) (clone $summaryQuery)->count(),
                'scaling_product_count' => $statusCounts['Scaling'],
                'testing_product_count' => $statusCounts['Testing'],
                'inactive_product_count' => $statusCounts['Inactive'],
            ],
        ]);
    }

    public function create(Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::CreateProducts->value, $workspace);
        $shops = Shop::where('workspace_id', $workspace->id)
            ->visibleTo(auth()->user(), $workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('workspaces/products/create', [
            'workspace' => $workspace,
            'shops' => $shops,
            'forms' => $this->formOptions($workspace),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::CreateProducts->value, $workspace);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:products,code,NULL,id,workspace_id,'.$workspace->id,
            'category' => 'required|string|max:255',
            // Scoped to this workspace so a form id from another one can't be
            // pinned onto the product.
            'product_form_id' => [
                'nullable',
                Rule::exists('product_forms', 'id')->where('workspace_id', $workspace->id),
            ],
            'status' => ['required', Rule::in(Product::STATUSES)],
            'winning_date' => 'nullable|date',
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
            'product_form_id' => $request->input('product_form_id') ?: null,
            'status' => $request->status,
            'winning_date' => $request->winning_date ?: null,
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
        $this->guardModule($workspace);
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
            'forms' => $this->formOptions($workspace),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Product $product)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::EditProducts->value, $workspace);

        if ($product->workspace_id !== $workspace->id) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:products,code,'.$product->id.',id,workspace_id,'.$workspace->id,
            'category' => 'required|string|max:255',
            // Scoped to this workspace so a form id from another one can't be
            // pinned onto the product.
            'product_form_id' => [
                'nullable',
                Rule::exists('product_forms', 'id')->where('workspace_id', $workspace->id),
            ],
            'status' => ['required', Rule::in(Product::STATUSES)],
            'winning_date' => 'nullable|date',
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
            'product_form_id' => $request->input('product_form_id') ?: null,
            'status' => $request->status,
            'winning_date' => $request->winning_date ?: null,
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
        $this->guardModule($workspace);
        $this->authorize(Permission::DeleteProducts->value, $workspace);

        if ($product->workspace_id !== $workspace->id) {
            abort(403, 'Unauthorized action.');
        }

        $product->delete();

        return redirect()->route('workspaces.products.index', $workspace->slug);
    }

    /**
     * Products are only reachable in a workspace that has the module switched
     * on. Owners bypass the permission checks below (they hold '*'), so the
     * module flag has to be enforced here rather than left to the policy.
     */
    /**
     * The delivery formats this workspace has defined, for the picker on the
     * create and edit screens.
     *
     * @return Collection<int, ProductForm>
     */
    private function formOptions(Workspace $workspace)
    {
        return ProductForm::ofWorkspace($workspace)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    private function guardModule(Workspace $workspace): void
    {
        abort_unless($workspace->products_module_enabled, 404);
    }
}
