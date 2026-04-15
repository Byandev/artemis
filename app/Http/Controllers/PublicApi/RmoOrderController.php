<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RmoOrderController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $query = User::where('status', 'active');

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', "%{$request->input('search')}%");
        }

        $users = $query->orderBy('name')->get(['id', 'name']);

        return response()->json(['users' => $users]);
    }

    public function assignedOrders(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'uuid'],
            'search' => ['nullable', 'string', 'min:2'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $orders = QueryBuilder::for(
            OrderForDelivery::where('workspace_id', $workspace->id)
                ->where('assignee_id', $request->input('user_id'))
                ->whereDate('delivery_date', now())
        )
            ->with([
                'order' => function ($query) {
                    $query->select(['id', 'order_number', 'parcel_status'])
                        ->with([
                            'shippingAddress' => function ($subQuery) {
                                $subQuery->select(['order_id', 'full_name', 'full_address', 'phone_number']);
                            },
                            'items' => function ($subQuery) {
                                $subQuery->select(['order_id', 'name', 'quantity']);
                            },
                        ]);
                },
                'page' => function ($query) {
                    $query->select(['id', 'name']);
                },
            ])
            ->allowedFilters([
                AllowedFilter::callback('page_id', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $query->whereIn('page_id', $values);
                }),
                AllowedFilter::callback('shop_id', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $query->whereIn('shop_id', $values);
                }),
                AllowedFilter::callback('status', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $query->whereIn('status', $values);
                }),
                AllowedFilter::callback('parcel_status', function ($query, $value) {
                    $values = is_string($value) ? explode(',', $value) : (array) $value;
                    $values = array_map('strtolower', $values);
                    $query->whereHas('order', function ($q) use ($values) {
                        $q->whereIn('parcel_status', $values);
                    });
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereHas('order.shippingAddress', function ($q) use ($value) {
                        $q->where('full_name', 'LIKE', "%$value%");
                    });
                }),
            ])
            ->paginate($request->input('per_page', 15));

        $orders->getCollection()->transform(function ($item) {
            return [
                'order_id' => $item->order_id,
                'order_number' => $item->order?->order_number,
                'customer_name' => $item->order?->shippingAddress?->full_name,
                'address' => $item->order?->shippingAddress?->full_address,
                'phone_number' => $item->order?->shippingAddress?->phone_number,
                'rider_name' => $item->rider_name,
                'rider_phone' => $item->rider_phone,
                'page_name' => $item->page?->name,
                'parcel_status' => $item->order?->parcel_status,
                'items' => $item->order?->items?->map(fn ($i) => [
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                ]),
                'status' => $item->status,
            ];
        });

        return response()->json($orders);
    }

    public function syncCallTracking(Request $request): JsonResponse
    {
        $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.order_id' => ['required', 'integer'],
            'orders.*.customer_call_attempts' => ['nullable', 'integer', 'min:0'],
            'orders.*.customer_call_duration' => ['nullable', 'integer', 'min:0'],
            'orders.*.customer_last_call' => ['nullable', 'date'],
            'orders.*.rider_call_attempts' => ['nullable', 'integer', 'min:0'],
            'orders.*.rider_call_duration' => ['nullable', 'integer', 'min:0'],
            'orders.*.rider_last_call' => ['nullable', 'date'],
        ]);

        $workspace = $request->attributes->get('workspace');
        $allowedFields = [
            'customer_call_attempts',
            'customer_call_duration',
            'customer_last_call',
            'rider_call_attempts',
            'rider_call_duration',
            'rider_last_call',
        ];

        $updated = 0;
        $notFound = [];

        foreach ($request->input('orders') as $item) {
            $order = OrderForDelivery::where('workspace_id', $workspace->id)
                ->where('order_id', $item['order_id'])
                ->first();

            if (! $order) {
                $notFound[] = $item['order_id'];
                continue;
            }

            $order->update(collect($item)->only($allowedFields)->filter()->all());
            $updated++;
        }

        return response()->json([
            'message' => "{$updated} orders updated.",
            'updated' => $updated,
            'not_found' => $notFound,
        ]);
    }
}
