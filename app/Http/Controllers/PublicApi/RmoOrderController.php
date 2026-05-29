<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Pancake\Models\OrderForDelivery;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RmoOrderController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        $workspace = $request->attributes->get('workspace');

        $belongsToWorkspace = $user->workspaces()->where('workspaces.id', $workspace->id)->exists()
            || $workspace->owner_id === $user->id;

        if (! $belongsToWorkspace) {
            return response()->json(['error' => 'User does not belong to this workspace.'], 403);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function assignedOrders(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $orders = QueryBuilder::for(OrderForDelivery::class)
            ->where('workspace_id', $workspace->id)
            ->where('assignee_user_id', $request->input('user_id'))
            ->whereDate('delivery_date', now())
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
                    $query->whereHas('order', function ($orderQuery) use ($values) {
                        $orderQuery->whereIn('parcel_status', $values);
                    });
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereHas('order.shippingAddress', function ($q) use ($value) {
                        $q->where('full_name', 'LIKE', "%{$value}%");
                    });
                }),
            ])
            ->with([
                'page:id,name',
                'shop:id,name',
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
            ])
            ->paginate($request->input('per_page', 10));

        $orders->getCollection()->transform(function ($item) {
            return [
                'order_id' => $item->order_id,
                'order_number' => $item->order?->order_number,
                'customer_name' => $item->order?->shippingAddress?->full_name,
                'address' => $item->order?->shippingAddress?->full_address,
                'phone_number' => $item->order?->shippingAddress?->phone_number,
                'rider_name' => $item->rider_name,
                'rider_phone' => $item->rider_phone,
                'page_id' => $item->page_id,
                'page_name' => $item->page?->name,
                'shop_id' => $item->shop_id,
                'shop_name' => $item->shop?->name,
                'items' => $item->order?->items?->map(fn ($i) => [
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                ]),
                'status' => $item->status,
                'parcel_status' => $item->order?->parcel_status,
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
