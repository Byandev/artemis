<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Http\Sorts\Order\CustomerNameSort;
use App\Http\Sorts\Order\PageNameSort;
use App\Http\Sorts\Order\RiderNameSort;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ForDeliveryController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        // Prepare lists of customers and riders for the dropdowns
        $baseResults = Order::forDeliveryToday()
            ->with([
                'parcelJourney:id,order_id,rider_name,note',
                'shippingAddress:id,order_id,full_name',
            ])
            ->get();

        $customers = $baseResults->pluck('shippingAddress.full_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $riders = $baseResults->map(function ($order) {
            return $order->parcelJourney?->rider_name;
        })
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Build filtered and sorted query using QueryBuilder
        $orders = QueryBuilder::for(Order::forDeliveryToday())
            ->with([
                'page:id,name',
                'parcelJourney:id,order_id,note,status,rider_name,rider_mobile,created_at',
                'shippingAddress:id,order_id,full_name,phone_number',
            ])
            ->allowedFilters([
                AllowedFilter::scope('page_name', 'filterByPageName'),
                AllowedFilter::scope('customer', 'filterByCustomer'),
                AllowedFilter::scope('rider', 'filterByRider'),
            ])
            ->allowedSorts([
                'tracking_code',
                'status_name',
                AllowedSort::custom('name', new PageNameSort),
                AllowedSort::custom('page.name', new PageNameSort),
                AllowedSort::custom('shipping_address.full_name', new CustomerNameSort),
                AllowedSort::custom('parcel_journey.rider_name', new RiderNameSort),
            ])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/rts/for-delivery-today', [
            'workspace' => $workspace,
            'orders' => $orders,
            'customers' => $customers,
            'riders' => $riders,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
