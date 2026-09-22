<?php

namespace Modules\Products\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Products\Models\TargetMarket;

class TargetMarketController extends Controller
{
    use AuthorizesRequests;

    /** What the rows-per-page picker offers, mirroring the data table's. */
    private const PER_PAGE = [10, 25, 50, 100, 500];

    public function index(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewTargetMarkets->value, $workspace);

        $search = trim((string) $request->input('search', ''));

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 10;

        $categories = TargetMarket::ofWorkspace($workspace)
            ->categories()
            // A hit on a sub category has to surface its category, or searching
            // for "Hypertension" would return nothing while the row sits inside
            // Cardiovascular.
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('children', fn ($c) => $c->where('name', 'like', "%{$search}%"))))
            ->with(['children' => fn ($q) => $q->select('id', 'parent_id', 'name', 'position')->ordered()])
            ->withCount('children')
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('workspaces/products/target-markets/index', [
            'workspace' => $workspace,
            'categories' => $categories,
            // The totals in the card header describe the whole tree, not the
            // page being looked at.
            'summary' => [
                'categories' => TargetMarket::ofWorkspace($workspace)->categories()->count(),
                'sub_categories' => TargetMarket::ofWorkspace($workspace)->subCategories()->count(),
            ],
            // Every category, for the "Add under" picker — the picker has to
            // offer the ones this page isn't showing too.
            'parents' => TargetMarket::ofWorkspace($workspace)
                ->categories()
                ->select('id', 'name', 'position')
                ->ordered()
                ->get(),
            'query' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageTargetMarkets->value, $workspace);

        $validated = $request->validate($this->rules($request, $workspace));

        $parentId = $validated['parent_id'] ?? null;

        TargetMarket::create([
            'workspace_id' => $workspace->id,
            'parent_id' => $parentId,
            'name' => $validated['name'],
            // New entries land at the end of their level; the page offers no
            // way to drop one in the middle.
            'position' => TargetMarket::nextPosition($workspace, $parentId),
        ]);

        return redirect()
            ->route('workspaces.products.target-markets.index', $workspace)
            ->with('success', "\"{$validated['name']}\" added.");
    }

    /**
     * Rename only. Moving a row to a different parent would have to re-check
     * the depth rule and re-home its children, and the page offers no way to
     * ask for it — the pencil edits the name.
     */
    public function update(Request $request, Workspace $workspace, TargetMarket $targetMarket)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageTargetMarkets->value, $workspace);
        $this->guard($workspace, $targetMarket);

        $validated = $request->validate([
            'name' => $this->nameRules($workspace, $targetMarket->parent_id, $targetMarket->id),
        ]);

        $targetMarket->update(['name' => $validated['name']]);

        return redirect()
            ->route('workspaces.products.target-markets.index', $workspace)
            ->with('success', "Renamed to \"{$validated['name']}\".");
    }

    public function destroy(Workspace $workspace, TargetMarket $targetMarket)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageTargetMarkets->value, $workspace);
        $this->guard($workspace, $targetMarket);

        $name = $targetMarket->name;

        // Sub categories go with the category — the foreign key cascades.
        $targetMarket->delete();

        return redirect()
            ->route('workspaces.products.target-markets.index', $workspace)
            ->with('success', "\"{$name}\" deleted.");
    }

    /**
     * Workspace owners hold '*', so the permission checks above wave them
     * through whether or not the workspace bought the module.
     */
    private function guardModule(Workspace $workspace): void
    {
        abort_unless($workspace->products_module_enabled, 404);
    }

    /**
     * Route-model binding resolves a row by id alone, so a member of one
     * workspace could otherwise reach another workspace's tree.
     */
    private function guard(Workspace $workspace, TargetMarket $market): void
    {
        abort_unless($market->workspace_id === $workspace->id, 404);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request, Workspace $workspace): array
    {
        return [
            // Null means "top level — new category". Anything else has to be a
            // category in this workspace: pointing at a sub category would make
            // a third level the page cannot draw.
            'parent_id' => [
                'nullable',
                Rule::exists('target_markets', 'id')
                    ->where('workspace_id', $workspace->id)
                    ->whereNull('parent_id'),
            ],
            'name' => $this->nameRules($workspace, $request->input('parent_id') ?: null),
        ];
    }

    /**
     * A name has to be unique among its siblings — two "Hypertension" rows
     * under Cardiovascular are indistinguishable on the page — but the same
     * name under a different category is fine.
     *
     * @return array<int, mixed>
     */
    private function nameRules(Workspace $workspace, mixed $parentId, ?int $ignoreId = null): array
    {
        $unique = Rule::unique('target_markets', 'name')
            ->where('workspace_id', $workspace->id)
            ->where(fn ($query) => $parentId === null
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', $parentId));

        return ['required', 'string', 'max:255', $ignoreId ? $unique->ignore($ignoreId) : $unique];
    }
}
