<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function shippingAddress(): \Illuminate\Database\Eloquent\Relations\HasOne|Order
    {
        return $this->hasOne(ShippingAddress::class);
    }

    public function parcelJourney(): \Illuminate\Database\Eloquent\Relations\HasOne|Order
    {
        return $this->hasOne(ParcelJourney::class);
    }

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        $table = $builder->getModel()->getTable();

        return $builder->where("{$table}.workspace_id", $workspace->id);
    }

    public function scopeApplyRtsFilters($query, $request)
    {
        // Filter by Page IDs
        if ($request->filled('page_ids')) {
            $query->whereIn('orders.page_id', (array) $request->page_ids);
        }

        // Filter by Shop IDs
        if ($request->filled('shop_ids')) {
            $query->whereIn('orders.shop_id', (array) $request->shop_ids);
        }

        // Filter by User IDs (via pages.owner_id)
        if ($request->filled('user_ids')) {
            $query->whereHas('page', function ($q) use ($request) {
                $q->whereIn('owner_id', (array) $request->user_ids);
            });
        }

        // Date range filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('orders.confirmed_at', [
                $request->start_date,
                $request->end_date,
            ]);
        }

        return $query;
    }

    public function parcelJourneyNotifications(): Order|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ParcelJourneyNotification::class);
    }

    public function scopeForDeliveryToday($builder)
    {
        return $builder->whereHas('parcelJourney', function ($q) {
            $q->where('status', 'On Delivery')
                ->whereDate('created_at', today());
        });
    }

    public function scopeFilterByRider($builder, ?string $rider)
    {
        if (! $rider) {
            return $builder;
        }

        return $builder->whereHas('parcelJourney', function ($q) use ($rider) {
            $q->where('rider_name', 'like', "%{$rider}%")
                ->orWhere('note', 'like', "%{$rider}%");
        });
    }

    public function scopeFilterByCustomer($builder, ?string $customer)
    {
        if (! $customer) {
            return $builder;
        }

        return $builder->whereHas('shippingAddress', function ($q) use ($customer) {
            $q->where('full_name', 'like', "%{$customer}%");
        });
    }

    public function scopeFilterByPageName($builder, ?string $pageName)
    {
        if (! $pageName) {
            return $builder;
        }

        return $builder->whereHas('page', function ($q) use ($pageName) {
            $q->where('name', 'like', "%{$pageName}%");
        });
    }

    public function scopeSortByColumn($builder, ?string $sortBy, string $sortDir = 'asc')
    {
        if (! $sortBy) {
            return $builder->orderBy('page_id');
        }

        $sortDir = in_array(strtolower($sortDir), ['asc', 'desc']) ? strtolower($sortDir) : 'asc';

        switch ($sortBy) {
            case 'name':
            case 'page.name':
                return $builder->leftJoin('pages', 'orders.page_id', '=', 'pages.id')
                    ->select('orders.*')
                    ->orderBy('pages.name', $sortDir);

            case 'tracking_code':
                return $builder->orderBy('orders.tracking_code', $sortDir);

            case 'shipping_address.full_name':
                return $builder->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'orders.id')
                    ->select('orders.*')
                    ->orderBy('shipping_addresses.full_name', $sortDir);

            case 'parcel_journey.rider_name':
                return $builder->leftJoin('parcel_journeys', 'parcel_journeys.order_id', '=', 'orders.id')
                    ->select('orders.*')
                    ->orderBy('parcel_journeys.rider_name', $sortDir);

            case 'status_name':
                return $builder->orderBy('orders.status_name', $sortDir);

            default:
                return $builder->orderBy($sortBy, $sortDir);
        }
    }
}
