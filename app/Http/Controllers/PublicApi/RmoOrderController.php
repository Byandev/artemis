<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User;

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
        $search = $request->input('search');

        $query = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_id', $request->input('user_id'))
            ->whereDate('delivery_date', now())
            ->with([
                'order' => function ($query) {
                    $query->select(['id', 'order_number'])
                        ->with([
                            'shippingAddress' => function ($subQuery) {
                                $subQuery->select(['order_id', 'full_name', 'full_address', 'phone_number']);
                            },
                            'items' => function ($subQuery) {
                                $subQuery->select(['order_id', 'name', 'quantity']);
                            },
                        ]);
                },
            ]);

        if ($search) {
            $query->whereHas('order.shippingAddress', function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%");
            });
        }

        $orders = $query->paginate($request->input('per_page', 15));

        $orders->getCollection()->transform(function ($item) {
            return [
                'order_id' => $item->order_id,
                'order_number' => $item->order?->order_number,
                'customer_name' => $item->order?->shippingAddress?->full_name,
                'address' => $item->order?->shippingAddress?->full_address,
                'phone_number' => $item->order?->shippingAddress?->phone_number,
                'rider_name' => $item->rider_name,
                'rider_phone' => $item->rider_phone,
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