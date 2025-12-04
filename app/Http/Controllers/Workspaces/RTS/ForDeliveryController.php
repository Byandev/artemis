<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\ParcelJourney;
use App\Models\Workspace;
use Inertia\Inertia;

class ForDeliveryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Workspace $workspace)
    {
        $parcelJourneys = ParcelJourney::with(['order.page', 'order.shippingAddress', 'rider', 'order.items.product'])
            ->whereIn('status', ['On Delivery', 'Departure', 'Arrival'])
            ->whereDate('created_at', now()->toDateString())
            ->whereHas('order', function ($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id)
                    ->where('status', 2);
            })
            ->get();

        $data = $parcelJourneys->map(function (ParcelJourney $pj) {
            $order = $pj->order;

            $page = $order->page?->name;
            $tracking = $order->tracking_code;
            $customer = $order->shippingAddress?->full_name;

            // Rider: prefer related Rider model, fallback to parsed note or rider_name field
            $rider = null;
            if ($pj->rider) {
                $rider = trim($pj->rider->name ?? '').' '.trim($pj->rider->mobile ?? '');
            } else {
                if (! empty($pj->rider_name) || ! empty($pj->rider_mobile)) {
                    $rider = trim(($pj->rider_name ?? '').' '.($pj->rider_mobile ?? ''));
                } elseif (preg_match_all('/【(.*?)】/', $pj->note, $matches) && isset($matches[1][1])) {
                    $sprinterInfo = $matches[1][1];
                    [$rider_name, $mobile] = array_map('trim', explode(':', $sprinterInfo));
                    $rider = trim($rider_name).' '.trim($mobile);
                }
            }

            // Product: take the first order item (product name or related product name)
            $product = null;
            if ($order->items && $order->items->count() > 0) {
                $firstItem = $order->items->first();
                $product = $firstItem->product_name ?? ($firstItem->product?->title ?? null);
            }

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'page' => $page,
                'product' => $product,
                'tracking_number' => $tracking,
                'customer' => $customer,
                'rider' => $rider,
                'status' => $pj->status ?? $order->parcel_status,
                'parcel_journey_created_at' => $pj->created_at,
            ];
        });

        return Inertia::render('workspaces/rts/for-delivery', [
            'workspace' => $workspace,
            'data' => $data,
        ]);
    }
}
