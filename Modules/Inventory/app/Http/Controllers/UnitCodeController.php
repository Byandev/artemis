<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Models\InventoryUnitCode;
use Modules\Inventory\Models\InventoryUnitCodeItem;

class UnitCodeController extends Controller
{
    use AuthorizesRequests;

    /** Columns the table may be sorted by (frontend field => DB column). */
    private const SORTABLE = [
        'sku' => 'sku',
        'unit_code' => 'unit_code',
        'total_amount' => 'total_amount',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('View Unit Code', $workspace);

        [$sortColumn, $sortDir, $sortParam] = $this->resolveSort($request);

        $unitCodes = $this->filtered($request, $workspace)
            // Items link by (workspace_id, unit_code); scope to this workspace.
            ->with([
                'items' => fn ($q) => $q
                    ->where('workspace_id', $workspace->id)
                    ->select(['id', 'workspace_id', 'unit_code', 'item_code', 'quantity']),
                'product:id,name',
            ])
            ->orderBy($sortColumn, $sortDir)
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return Inertia::render('workspaces/gencys/unit-codes/index', [
            'workspace' => $workspace,
            'unitCodes' => $unitCodes,
            'products' => Product::ofWorkspace($workspace)->orderBy('name')->get(['id', 'name']),
            'query' => [
                'sort' => $sortParam,
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /** Set (or clear) the product a single unit code maps to. */
    public function updateProduct(Request $request, Workspace $workspace, InventoryUnitCode $unitCode): RedirectResponse
    {
        $this->authorize('Edit Unit Code', $workspace);
        abort_unless($unitCode->workspace_id === $workspace->id, 404);

        $data = $request->validate([
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $unitCode->update(['product_id' => $data['product_id'] ?? null]);

        return back()->with('success', 'Product updated.');
    }

    /** Set (or clear) the product on several unit codes at once. */
    public function bulkUpdateProduct(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('Edit Unit Code', $workspace);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $count = InventoryUnitCode::where('workspace_id', $workspace->id)
            ->whereIn('id', $data['ids'])
            ->update(['product_id' => $data['product_id'] ?? null]);

        return back()->with('success', "{$count} unit code(s) updated.");
    }

    /**
     * Kick off an on-demand ERP unit-code sync for this workspace by calling the
     * n8n webhook directly. n8n scrapes the codes and posts them back to the
     * public bulk-sync callback endpoint, authenticating with this workspace's key.
     */
    public function sync(Workspace $workspace): RedirectResponse
    {
        $this->authorize('Create Unit Code', $workspace);

        $webhookUrl = config('services.n8n.inventory_unit_code_webhook_url')
            ?: config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            return back()->with('error', 'Unit code sync is not configured yet. Please contact support.');
        }

        $apiKey = $workspace->apiKeys()->first();

        if (blank($workspace->erp_username) || blank($workspace->erp_password) || ! $apiKey) {
            return back()->with('error', 'This workspace is not connected to the ERP. Add ERP credentials and an API key first.');
        }

        $callbackBase = rtrim(config('app.url'), '/');

        $response = Http::timeout(30)->post($webhookUrl, [
            'workspace_api_key' => $apiKey->reveal(),
            'erp_username' => $workspace->erp_username,
            'erp_password' => $workspace->erp_password,
            'webhook_url' => "https://6984-152-32-104-235.ngrok-free.app/api/v1/public/inventory/unit-codes/bulk-sync",
        ]);

        if (! $response->successful()) {
            return back()->with('error', 'Something went wrong while syncing.'."\n".$response->body());
        }

        return back()->with('success', 'Unit code sync started. New codes from the ERP will appear here shortly.');
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('Create Unit Code', $workspace);

        $data = $this->validateData($request, $workspace);

        DB::transaction(function () use ($workspace, $data) {
            $unitCode = InventoryUnitCode::create([
                'workspace_id' => $workspace->id,
                'sku' => $data['sku'],
                'unit_code' => $data['unit_code'],
                'total_amount' => $data['total_amount'] ?? null,
            ]);

            $this->syncItems($workspace->id, $unitCode->unit_code, $data['items'] ?? []);
        });

        return redirect()
            ->route('workspaces.gencys.unit-codes.index', $workspace->slug)
            ->with('success', 'Unit code created successfully.');
    }

    public function update(Request $request, Workspace $workspace, InventoryUnitCode $unitCode): RedirectResponse
    {
        $this->authorize('Edit Unit Code', $workspace);
        abort_unless($unitCode->workspace_id === $workspace->id, 404);

        $data = $this->validateData($request, $workspace, $unitCode);

        DB::transaction(function () use ($workspace, $unitCode, $data) {
            // Items link by unit_code, so clear the old set if the code is renamed.
            $original = $unitCode->unit_code;

            $unitCode->update([
                'sku' => $data['sku'],
                'unit_code' => $data['unit_code'],
                'total_amount' => $data['total_amount'] ?? null,
            ]);

            if ($original !== $unitCode->unit_code) {
                InventoryUnitCodeItem::where('workspace_id', $workspace->id)
                    ->where('unit_code', $original)
                    ->delete();
            }

            $this->syncItems($workspace->id, $unitCode->unit_code, $data['items'] ?? []);
        });

        return redirect()
            ->route('workspaces.gencys.unit-codes.index', $workspace->slug)
            ->with('success', 'Unit code updated successfully.');
    }

    public function destroy(Workspace $workspace, InventoryUnitCode $unitCode): RedirectResponse
    {
        $this->authorize('Delete Unit Code', $workspace);
        abort_unless($unitCode->workspace_id === $workspace->id, 404);

        DB::transaction(function () use ($workspace, $unitCode) {
            InventoryUnitCodeItem::where('workspace_id', $workspace->id)
                ->where('unit_code', $unitCode->unit_code)
                ->delete();

            $unitCode->delete();
        });

        return redirect()
            ->route('workspaces.gencys.unit-codes.index', $workspace->slug)
            ->with('success', 'Unit code deleted successfully.');
    }

    /**
     * Validate the unit-code payload. `unit_code` is unique per workspace.
     *
     * @return array<string, mixed>
     */
    private function validateData(Request $request, Workspace $workspace, ?InventoryUnitCode $unitCode = null): array
    {
        return $request->validate([
            'sku' => ['nullable', 'string', 'max:255'],
            'unit_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_unit_codes', 'unit_code')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($unitCode?->id),
            ],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.item_code' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * Replace a unit code's items with the given set. Items link to their parent
     * by (workspace_id, unit_code), so the whole set is cleared and re-inserted.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(int $workspaceId, string $unitCode, array $items): void
    {
        InventoryUnitCodeItem::where('workspace_id', $workspaceId)
            ->where('unit_code', $unitCode)
            ->delete();

        $rows = collect($items)
            ->filter(fn ($item) => filled($item['item_code'] ?? null))
            ->map(fn ($item) => [
                'workspace_id' => $workspaceId,
                'unit_code' => $unitCode,
                'item_code' => $item['item_code'],
                'quantity' => $item['quantity'] ?? null,
            ])
            ->all();

        if (! empty($rows)) {
            InventoryUnitCodeItem::insert($rows);
        }
    }

    /** Workspace-scoped query with the request's search filter applied. */
    private function filtered(Request $request, Workspace $workspace): Builder
    {
        $query = InventoryUnitCode::query()
            ->where('workspace_id', $workspace->id)
            // Team scoping: a unit code is visible if any of its items maps (by SKU)
            // to an inventory item in the user's team (no-op for unrestricted users).
            ->visibleTo($request->user(), $workspace);

        if ($search = $request->input('filter.search')) {
            $query->where(function (Builder $q) use ($search) {
                foreach (['sku', 'unit_code'] as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        return $query;
    }

    /** Resolve the sort param ("-unit_code") into [column, direction, normalized param]. */
    private function resolveSort(Request $request): array
    {
        $raw = (string) $request->input('sort', 'unit_code');
        $desc = str_starts_with($raw, '-');
        $field = $desc ? substr($raw, 1) : $raw;

        $column = self::SORTABLE[$field] ?? 'unit_code';
        $field = array_search($column, self::SORTABLE, true) ?: 'unit_code';

        return [$column, $desc ? 'desc' : 'asc', ($desc ? '-' : '').$field];
    }
}
