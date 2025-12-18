<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'workspace_id',
        'owner_id',
        'title',
        'name',
        'code',
        'category',
        'status',
        'description',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')
            ->where('collection_name', 'PRODUCT_IMAGE');
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }
    public function scopeWithAdvertisingSales(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        return $query->selectSub(function ($q) use ($start_date, $end_date) {
            $q->from('ad_records')
                ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                ->join('pages', 'pages.id', '=', 'ads.page_id')
                ->whereColumn('pages.product_id', 'products.id')
                ->selectRaw('COALESCE(SUM(ad_records.sales), 0)');
        }, 'advertising_sales');
    }

    public function scopeWithAdSpent(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        return $query->selectSub(function ($q) use ($start_date, $end_date) {
            $q->from('ad_records')
                ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                ->join('pages', 'pages.id', '=', 'ads.page_id')
                ->whereColumn('pages.product_id', 'products.id')
                ->when($start_date, function ($q1) use ($start_date) {
                    $q1->whereDate('ad_records.date', '>=', $start_date);
                })
                ->when($end_date, function ($q1) use ($end_date) {
                    $q1->whereDate('ad_records.date', '<=', $end_date);
                })
                ->selectRaw('COALESCE(SUM(ad_records.spend), 0)');
        }, 'ad_spent');
    }

    public function scopeWithSales(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        return $query->selectSub(function ($q) use ($start_date, $end_date) {
            $q->from('orders')
                ->join('pages', 'pages.id', '=', 'orders.page_id')
                ->whereColumn('pages.product_id', 'products.id')
                ->when($start_date, function ($q1) use ($start_date) {
                    $q1->whereDate('orders.confirmed_at', '>=', $start_date);
                })
                ->when($end_date, function ($q1) use ($end_date) {
                    $q1->whereDate('orders.confirmed_at', '<=', $end_date);
                })
                ->selectRaw('COALESCE(SUM(orders.total_amount), 0)');
        }, 'sales');
    }

    public function scopeWithRoas(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        $base = (clone $query)
            ->select('products.*')
            ->withSales($start_date, $end_date)
            ->withAdSpent($start_date, $end_date);

        return $query
            ->fromSub($base, 'p')
            ->select('p.*')
            ->selectRaw("
            CASE
                WHEN COALESCE(p.ad_spent, 0) = 0 THEN 0
                ELSE COALESCE(p.sales, 0) / p.ad_spent
            END AS roas
        ");
    }

    public function scopeWithDeliveredCount(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        return $query->selectSub(function ($q) use ($start_date, $end_date) {
            $q->from('orders')
                ->join('pages', 'pages.id', '=', 'orders.page_id')
                ->whereColumn('pages.product_id', 'products.id')
                ->whereNotNull('orders.confirmed_at')
                ->whereNotNull('orders.delivered_at')
                ->when($start_date, function ($q1) use ($start_date) {
                    $q1->whereDate('orders.confirmed_at', '>=', $start_date);
                })
                ->when($end_date, function ($q1) use ($end_date) {
                    $q1->whereDate('orders.confirmed_at', '<=', $end_date);
                })
                ->selectRaw('COUNT(*)');
        }, 'delivered_count');
    }

    public function scopeWithReturningCount(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        return $query->selectSub(function ($q) use ($start_date, $end_date) {
            $q->from('orders')
                ->join('pages', 'pages.id', '=', 'orders.page_id')
                ->whereColumn('pages.product_id', 'products.id')
                ->whereNotNull('orders.confirmed_at')
                ->whereNotNull('orders.returning_at')
                ->when($start_date, function ($q1) use ($start_date) {
                    $q1->whereDate('orders.confirmed_at', '>=', $start_date);
                })
                ->when($end_date, function ($q1) use ($end_date) {
                    $q1->whereDate('orders.confirmed_at', '<=', $end_date);
                })
                ->selectRaw('COUNT(*)');
        }, 'returning_count');
    }

    public function scopeWithRts(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        $base = (clone $query)
            ->select('products.*')
            ->withDeliveredCount($start_date, $end_date)
            ->withReturningCount($start_date, $end_date);

        return $query
            ->fromSub($base, 'p')
            ->select('p.*')
            ->selectRaw("
            COALESCE(
                p.returning_count / NULLIF((p.returning_count + p.delivered_count), 0),
                0
            ) AS rts
        ");
    }
}
