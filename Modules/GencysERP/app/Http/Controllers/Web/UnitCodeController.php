<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\GencysUnitCode;

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
            ->with('items:id,gencys_unit_code_id,unit_code,inventory_item_code,quantity,price')
            ->orderBy($sortColumn, $sortDir)
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return Inertia::render('workspaces/gencys/unit-codes/index', [
            'workspace' => $workspace,
            'unitCodes' => $unitCodes,
            'query' => [
                'sort' => $sortParam,
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('Create Unit Code', $workspace);

        $data = $this->validateData($request, $workspace);

        DB::transaction(function () use ($workspace, $data) {
            $unitCode = GencysUnitCode::create([
                // Manual entries have no Gencys row_id; the local id auto-increments.
                'workspace_id' => $workspace->id,
                'sku' => $data['sku'],
                'unit_code' => $data['unit_code'],
                'total_amount' => $data['total_amount'] ?? null,
            ]);

            $this->syncItems($unitCode, $data['items'] ?? []);
        });

        return redirect()
            ->route('workspaces.gencys.unit-codes.index', $workspace->slug)
            ->with('success', 'Unit code created successfully.');
    }

    public function update(Request $request, Workspace $workspace, GencysUnitCode $unitCode): RedirectResponse
    {
        $this->authorize('Edit Unit Code', $workspace);
        abort_unless($unitCode->workspace_id === $workspace->id, 404);

        $data = $this->validateData($request, $workspace, $unitCode);

        DB::transaction(function () use ($unitCode, $data) {
            $unitCode->update([
                'sku' => $data['sku'],
                'unit_code' => $data['unit_code'],
                'total_amount' => $data['total_amount'] ?? null,
            ]);

            $this->syncItems($unitCode, $data['items'] ?? []);
        });

        return redirect()
            ->route('workspaces.gencys.unit-codes.index', $workspace->slug)
            ->with('success', 'Unit code updated successfully.');
    }

    public function destroy(Workspace $workspace, GencysUnitCode $unitCode): RedirectResponse
    {
        $this->authorize('Delete Unit Code', $workspace);
        abort_unless($unitCode->workspace_id === $workspace->id, 404);

        $unitCode->delete();

        return redirect()
            ->route('workspaces.gencys.unit-codes.index', $workspace->slug)
            ->with('success', 'Unit code deleted successfully.');
    }

    /**
     * Validate the unit-code payload. `unit_code` is unique per workspace.
     *
     * @return array<string, mixed>
     */
    private function validateData(Request $request, Workspace $workspace, ?GencysUnitCode $unitCode = null): array
    {
        return $request->validate([
            'sku' => ['nullable', 'string', 'max:255'],
            'unit_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('gencys_unit_codes', 'unit_code')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($unitCode?->id),
            ],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.inventory_item_code' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * Replace a unit code's inventory items with the given set. Each line's
     * `unit_code` mirrors the parent so the row is self-describing.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(GencysUnitCode $unitCode, array $items): void
    {
        $unitCode->items()->delete();

        $rows = collect($items)
            ->filter(fn ($item) => filled($item['inventory_item_code'] ?? null))
            ->map(fn ($item) => [
                'unit_code' => $unitCode->unit_code,
                'inventory_item_code' => $item['inventory_item_code'],
                'quantity' => $item['quantity'] ?? null,
                'price' => $item['price'] ?? null,
            ])
            ->all();

        if (! empty($rows)) {
            $unitCode->items()->createMany($rows);
        }
    }

    /** Workspace-scoped query with the request's search filter applied. */
    private function filtered(Request $request, Workspace $workspace): Builder
    {
        $query = GencysUnitCode::query()->where('workspace_id', $workspace->id);

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
