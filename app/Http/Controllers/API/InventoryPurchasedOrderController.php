<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class InventoryPurchasedOrderController extends Controller
{
    private const STATUS_LABELS = [
        'For Approval',
        'Approved',
        'To Pay',
        'Paid',
        'For Purchase',
        'Waiting For Delivery',
        'Delivered',
        'Cancelled',
    ];
    private const DEFAULT_PER_PAGE = 50;
    private const TABLE = 'inventory_purchased_orders';

    private function isValidStatusInput(mixed $value): bool
    {
        if (is_numeric($value)) {
            $code = (int) $value;
            return $code >= 1 && $code <= count(self::STATUS_LABELS);
        }

        return in_array((string) $value, self::STATUS_LABELS, true);
    }

    private function statusLabelFromCode(int $code): string
    {
        return self::STATUS_LABELS[$code - 1] ?? self::STATUS_LABELS[0];
    }

    private function statusCodeFromLabel(string $label): int
    {
        $index = array_search($label, self::STATUS_LABELS, true);

        return $index === false ? 1 : $index + 1;
    }

    private function statusCodeFromInput(mixed $value): int
    {
        if (is_numeric($value)) {
            $code = (int) $value;
            if ($code >= 1 && $code <= count(self::STATUS_LABELS)) {
                return $code;
            }
        }

        return $this->statusCodeFromLabel((string) $value);
    }

    private function normalizeIssueDate(object $row): array
    {
        $data = (array) $row;
        $data['status'] = $this->statusLabelFromCode((int) ($data['status'] ?? 1));

        return $data;
    }

    private function assertWorkspaceMembership(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    private function getAvailableYears(Request $request): Collection
    {
        return DB::table(self::TABLE)
            ->where('user_id', $request->user()->id)
            ->whereNotNull('issue_date')
            ->selectRaw('YEAR(issue_date) as year')
            ->distinct()
            ->orderBy('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values();
    }

    private function baseQuery(Request $request): Builder
    {
        return DB::table(self::TABLE)
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

    private function applyFilters(Builder $query, array $validated): void
    {
        if (isset($validated['status'])) {
            $query->where('status', $this->statusCodeFromInput($validated['status']));
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

    private function statusRule(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            function ($attribute, $value, $fail) {
                if ($value !== null && ! $this->isValidStatusInput($value)) {
                    $fail('The selected '.$attribute.' is invalid.');
                }
            },
        ];
    }

    private function purchasedOrderRules(): array
    {
        return [
            'issue_date' => ['required', 'date'],
            'delivery_no' => ['nullable', 'string', 'max:120'],
            'cust_po_no' => ['nullable', 'string', 'max:120'],
            'control_no' => ['nullable', 'string', 'max:120'],
            'item' => ['required', 'string', 'max:160'],
            'cog_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => $this->statusRule(),
        ];
    }

    private function findOwnedOrder(Request $request, int $order): object
    {
        $ownedOrder = DB::table(self::TABLE)
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
                'user_id',
            ])
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $ownedOrder) {
            abort(404);
        }

        return $ownedOrder;
    }

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $availableYears = $this->getAvailableYears($request);

        $validated = $request->validate([
            'status' => $this->statusRule(false),
            'q' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'show_all' => ['nullable', 'boolean'],
        ]);

        $query = $this->baseQuery($request);
        $this->applyFilters($query, $validated);

        if (! empty($validated['show_all'])) {
            $allRows = $query
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (object $row) => $this->normalizeIssueDate($row));

            $total = $allRows->count();

            return response()->json([
                'data' => $allRows,
                'available_years' => $availableYears,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $total,
                'total' => $total,
            ]);
        }

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $paginated = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginated->setCollection(
            $paginated->getCollection()->map(fn (object $row) => $this->normalizeIssueDate($row))
        );

        $response = $paginated->toArray();
        $response['available_years'] = $availableYears;

        return response()->json($response);
    }

    public function updateStatus(Request $request, Workspace $workspace, int $order): JsonResponse
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $validated = $request->validate([
            'status' => $this->statusRule(),
        ]);

        $ownedOrder = $this->findOwnedOrder($request, $order);
        $nextStatusCode = $this->statusCodeFromInput($validated['status']);

        if ((int) $ownedOrder->status === $nextStatusCode) {
            return response()->json([
                'message' => 'Status unchanged.',
                'data' => $this->normalizeIssueDate($ownedOrder),
            ]);
        }

        DB::table(self::TABLE)
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->update([
                'status' => $nextStatusCode,
            ]);

        $updatedOrder = $this->findOwnedOrder($request, $order);

        return response()->json([
            'message' => 'Status updated.',
            'data' => $this->normalizeIssueDate($updatedOrder),
        ]);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $validated = $request->validate($this->purchasedOrderRules());

        $createdId = DB::table(self::TABLE)->insertGetId([
            'user_id' => $request->user()->id,
            'issue_date' => $validated['issue_date'],
            'delivery_no' => $validated['delivery_no'] ?? null,
            'cust_po_no' => $validated['cust_po_no'] ?? null,
            'control_no' => $validated['control_no'] ?? null,
            'item' => trim($validated['item']),
            'cog_amount' => (float) ($validated['cog_amount'] ?? 0),
            'delivery_fee' => (float) ($validated['delivery_fee'] ?? 0),
            'total_amount' => (float) ($validated['total_amount'] ?? 0),
            'status' => $this->statusCodeFromInput($validated['status']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = $this->findOwnedOrder($request, (int) $createdId);

        return response()->json([
            'message' => 'Purchased order created.',
            'data' => $this->normalizeIssueDate($created),
        ], 201);
    }

    public function update(Request $request, Workspace $workspace, int $order): JsonResponse
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $this->findOwnedOrder($request, $order);

        $validated = $request->validate($this->purchasedOrderRules());

        DB::table(self::TABLE)
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->update([
                'issue_date' => $validated['issue_date'],
                'delivery_no' => $validated['delivery_no'] ?? null,
                'cust_po_no' => $validated['cust_po_no'] ?? null,
                'control_no' => $validated['control_no'] ?? null,
                'item' => trim($validated['item']),
                'cog_amount' => (float) ($validated['cog_amount'] ?? 0),
                'delivery_fee' => (float) ($validated['delivery_fee'] ?? 0),
                'total_amount' => (float) ($validated['total_amount'] ?? 0),
                'status' => $this->statusCodeFromInput($validated['status']),
                'updated_at' => now(),
            ]);

        $updatedOrder = $this->findOwnedOrder($request, $order);

        return response()->json([
            'message' => 'Purchased order updated.',
            'data' => $this->normalizeIssueDate($updatedOrder),
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, int $order): JsonResponse
    {
        $this->assertWorkspaceMembership($request, $workspace);

        $ownedOrder = $this->findOwnedOrder($request, $order);

        $deliveryNo = $ownedOrder->delivery_no;
        DB::table(self::TABLE)
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'message' => 'Purchased order deleted.',
            'data' => [
                'id' => $order,
                'delivery_no' => $deliveryNo,
            ],
        ]);
    }
}
