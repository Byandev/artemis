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

        // Prepare lists of customers and riders for the dropdowns (from today's On Delivery parcel journeys)
        $baseResults = Order::whereHas('parcelJourney', function ($q) {
            $q->where('status', 'On Delivery')
                ->whereDate('created_at', today());
        })
            ->with([
                'parcelJourney:id,order_id,rider_name',
                'shippingAddress:id,order_id,full_name',
            ])
            ->get();

        $customers = $baseResults->pluck('shippingAddress.full_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $riders = $baseResults->pluck('parcelJourney.rider_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // build base query
        $query = Order::whereHas('parcelJourney', function ($q) use ($rider) {
            $q->where('status', 'On Delivery')
                ->whereDate('created_at', today());

            if ($rider) {
                $q->where('rider_name', 'like', "%{$rider}%");
            }
        })
            ->when($customer, function ($q, $customer) {
                $q->whereHas('shippingAddress', function ($sq) use ($customer) {
                    $sq->where('full_name', 'like', "%{$customer}%");
                });
            })
            ->when($pageName, function ($q, $pageName) {
                $q->whereHas('page', function ($pq) use ($pageName) {
                    $pq->where('name', 'like', "%{$pageName}%");
                });
            });

        // sorting
        $sortBy = $request->query('sort_by');
        $sortDir = strtolower($request->query('sort_dir', 'asc'));
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'asc';

        // map sort keys to actual columns / joins
        if ($sortBy) {
            switch ($sortBy) {
                case 'name':
                case 'page.name':
                    // sort by page name
                    $query->leftJoin('pages', 'orders.page_id', '=', 'pages.id')
                        ->select('orders.*')
                        ->orderBy('pages.name', $sortDir);
                    break;
                case 'tracking_code':
                    $query->orderBy('orders.tracking_code', $sortDir);
                    break;
                case 'shipping_address.full_name':
                    $query->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'orders.id')
                        ->select('orders.*')
                        ->orderBy('shipping_addresses.full_name', $sortDir);
                    break;
                case 'parcel_journey.rider_name':
                    $query->leftJoin('parcel_journeys', 'parcel_journeys.order_id', '=', 'orders.id')
                        ->select('orders.*')
                        ->orderBy('parcel_journeys.rider_name', $sortDir);
                    break;
                case 'status_name':
                    $query->orderBy('orders.status_name', $sortDir);
                    break;
                default:
                    // fallback: try ordering by column on orders
                    $query->orderBy($sortBy, $sortDir);
                    break;
            }
        } else {
            // default ordering
            $query->orderBy('page_id');
        }

        $orders = $query->with([
            'page:id,name',
            'parcelJourney:id,order_id,status,rider_name,rider_mobile,created_at',
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
