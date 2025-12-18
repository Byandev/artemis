<?php

namespace App\Models;

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

    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        return $builder->where('workspace_id', $workspace->id);
    }

    public function scopeWithAdvertisingSales($query)
    {
        return $query->selectSub(function ($q) {
            $q->from('ad_records')
                ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                ->join('pages', 'pages.id', '=', 'ads.page_id')
                ->whereColumn('pages.product_id', 'products.id')
                ->selectRaw('COALESCE(SUM(ad_records.sales), 0)');
        }, 'advertising_sales');
    }

    public function scopeWithAdSpent($query)
    {
        return $query->selectSub(function ($q) {
            $q->from('ad_records')
                ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                ->join('pages', 'pages.id', '=', 'ads.page_id')
                ->whereColumn('pages.product_id', 'products.id')
                ->selectRaw('COALESCE(SUM(ad_records.spend), 0)');
        }, 'ad_spent');
    }

    public function scopeWithSales($query)
    {
        return $query->selectSub(function ($q) {
            $q->from('orders')
                ->join('pages', 'pages.id', '=', 'orders.page_id')
                ->whereColumn('pages.product_id', 'products.id')
                ->selectRaw('COALESCE(SUM(orders.total_amount), 0)');
        }, 'sales');
    }

    public function scopeWithRoas($query)
    {
        return $query->selectRaw("
            CASE
                WHEN (
                    SELECT COALESCE(SUM(ar.spend), 0)
                    FROM ad_records ar
                    JOIN ads a ON a.id = ar.ad_id
                    JOIN pages p ON p.id = a.page_id
                    WHERE p.product_id = products.id
                ) = 0
                THEN 0
                ELSE (
                    (
                        SELECT COALESCE(SUM(o.total_amount), 0)
                        FROM orders o
                        JOIN pages p2 ON p2.id = o.page_id
                        WHERE p2.product_id = products.id
                    ) / (
                        SELECT COALESCE(SUM(ar2.spend), 0)
                        FROM ad_records ar2
                        JOIN ads a2 ON a2.id = ar2.ad_id
                        JOIN pages p3 ON p3.id = a2.page_id
                        WHERE p3.product_id = products.id
                    )
                )
            END AS roas
        ");
    }

    public function scopeWithRts($query)
    {
        return $query->selectSub(function ($q) {
            $q->selectRaw("
                COALESCE(
                    (
                        (SELECT COUNT(*)
                         FROM orders o
                         JOIN pages p2 ON p2.id = o.page_id
                         WHERE p2.product_id = products.id
                           AND o.confirmed_at IS NOT NULL
                           AND o.returning_at IS NOT NULL)
                        /
                        NULLIF(
                            (
                                (SELECT COUNT(*)
                                 FROM orders o2
                                 JOIN pages p3 ON p3.id = o2.page_id
                                 WHERE p3.product_id = products.id
                                   AND o2.confirmed_at IS NOT NULL
                                   AND o2.returning_at IS NOT NULL)
                                +
                                (SELECT COUNT(*)
                                 FROM orders o3
                                 JOIN pages p4 ON p4.id = o3.page_id
                                 WHERE p4.product_id = products.id
                                   AND o3.confirmed_at IS NOT NULL
                                   AND o3.delivered_at IS NOT NULL)
                            ),
                            0
                        )
                    ),
                    0
                )
            ");
        }, 'rts');
    }
}
