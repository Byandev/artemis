<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryPurchasedOrder;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryPurchasedOrderController extends Controller
{
    private const STATUS_VALUES = '1,2,3,4,5,6,7,8';

    private function normalizeIssueDate(InventoryPurchasedOrder $row): array
    {
        $data = $row->toArray();
        $data['issue_date'] = $row->getRawOriginal('issue_date');

        return $data;
    }

    private function assertWorkspaceMembership(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    private function getAvailableYears(Request $request)
    {
        return InventoryPurchasedOrder::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('issue_date')
            ->selectRaw('YEAR(issue_date) as year')
            ->distinct()
            ->orderBy('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values();
    }

    private function baseQuery(Request $request)
    {
        return InventoryPurchasedOrder::query()
            ->select([
                'id',
                'issue_date',
                'delivery_no',
                'cust_po_no',
                'control_no',
                'item',
                'cog_amount',
                'delivery_fee',
                'total_amount',
                'status',
            ])
            ->where('user_id', $request->user()->id);
    }

    private function applyFilters($query, array $validated): void
    {
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['q'])) {
            $q = trim($validated['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('delivery_no', 'like', "%{$q}%")
                    ->orWhere('cust_po_no', 'like', "%{$q}%")
                    ->orWhere('control_no', 'like', "%{$q}%")
                    ->orWhere('item', 'like', "%{$q}%");
            });
        }

        if (! empty($validated['start_date'])) {
            $query->whereDate('issue_date', '>=', $validated['start_date']);
        }

        if (! empty($validated['end_date'])) {
            $query->whereDate('issue_date', '<=', $validated['end_date']);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $availableYears = $this->getAvailableYears($request);

        $validated = $request->validate([
            'status' => ['nullable', 'integer', 'in:'.self::STATUS_VALUES],
            'q' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'show_all' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $start = Carbon::parse($validated['start_date']);
            $end = Carbon::parse($validated['end_date']);
            if ($start->diffInDays($end) > 93) {
                throw ValidationException::withMessages([
                    'end_date' => ['Date range cannot exceed 93 days.'],
                ]);
            }
        }

        $query = $this->baseQuery($request);
        $this->applyFilters($query, $validated);

        if (! empty($validated['show_all'])) {
            $allRows = $query
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (InventoryPurchasedOrder $row) => $this->normalizeIssueDate($row));

            return response()->json([
                'data' => $allRows,
                'available_years' => $availableYears,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $allRows->count(),
                'total' => $allRows->count(),
            ]);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);

        $paginated = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginated->setCollection(
            $paginated->getCollection()->map(fn (InventoryPurchasedOrder $row) => $this->normalizeIssueDate($row))
        );

        $response = $paginated->toArray();
        $response['available_years'] = $availableYears;

        return response()->json($response);
    }

    public function updateStatus(Request $request, Workspace $workspace, int $order)
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:'.self::STATUS_VALUES],
        ]);

        $ownedOrder = InventoryPurchasedOrder::query()
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ((int) $ownedOrder->status === (int) $validated['status']) {
            return response()->json([
                'message' => 'Status unchanged.',
                'data' => $ownedOrder,
            ]);
        }

        $ownedOrder->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Status updated.',
            'data' => $ownedOrder->fresh(),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $validated = $request->validate([
            'issue_date' => ['required', 'date'],
            'delivery_no' => ['nullable', 'string', 'max:120'],
            'cust_po_no' => ['nullable', 'string', 'max:120'],
            'control_no' => ['nullable', 'string', 'max:120'],
            'item' => ['required', 'string', 'max:160'],
            'cog_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'integer', 'in:'.self::STATUS_VALUES],
        ]);

        $created = InventoryPurchasedOrder::query()->create([
            'user_id' => $request->user()->id,
            'issue_date' => $validated['issue_date'],
            'delivery_no' => $validated['delivery_no'] ?? null,
            'cust_po_no' => $validated['cust_po_no'] ?? null,
            'control_no' => $validated['control_no'] ?? null,
            'item' => trim($validated['item']),
            'cog_amount' => (float) ($validated['cog_amount'] ?? 0),
            'delivery_fee' => (float) ($validated['delivery_fee'] ?? 0),
            'total_amount' => (float) ($validated['total_amount'] ?? 0),
            'status' => (int) $validated['status'],
        ]);

        return response()->json([
            'message' => 'Purchased order created.',
            'data' => $this->normalizeIssueDate($created),
        ], 201);
    }

    public function destroy(Request $request, Workspace $workspace, int $order)
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $ownedOrder = InventoryPurchasedOrder::query()
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $deliveryNo = $ownedOrder->delivery_no;
        $ownedOrder->delete();

        return response()->json([
            'message' => 'Purchased order deleted.',
            'data' => [
                'id' => $order,
                'delivery_no' => $deliveryNo,
            ],
        ]);
    }
}
