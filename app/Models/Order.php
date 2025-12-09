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
}
