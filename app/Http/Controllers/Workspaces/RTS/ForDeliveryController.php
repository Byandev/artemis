<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ForDeliveryController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $customer = $request->query('customer');
        $rider = $request->query('rider');
        $pageName = $request->query('page_name');
        $sortBy = $request->query('sort_by');
        $sortDir = $request->query('sort_dir', 'asc');

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

        // Build filtered and sorted query
        $orders = Order::forDeliveryToday()
            ->filterByRider($rider)
            ->filterByCustomer($customer)
            ->filterByPageName($pageName)
            ->sortByColumn($sortBy, $sortDir)
            ->with([
                'page:id,name',
                'parcelJourney:id,order_id,note,status,rider_name,rider_mobile,created_at',
                'shippingAddress:id,order_id,full_name,phone_number',
            ])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/rts/for-delivery-today', [
            'workspace' => $workspace,
            'orders' => $orders,
            'customers' => $customers,
            'riders' => $riders,
        ]);
    }
}
