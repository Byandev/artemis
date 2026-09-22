<?php

namespace Modules\Products\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Products\Database\Factories\ProductFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, ScopesToVisibleTeams;

    /**
     * The factory lives in the module, so the default App\Models -> Database\Factories
     * resolver can no longer find it.
     */
    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * A product reaches its teams through the shops that sell it
     * (shops.product_id -> shops.teams), the same path InventoryItem uses via
     * `product.shops.teams`. Lets `visibleTo()` scope the product list to the
     * active team.
     */
    protected function visibilityTeamRelation(): string
    {
        return 'shops.teams';
    }

    /**
     * The product lifecycle stages, in order. Mirrors the `status` enum on the
     * products table and resources/js/constants/product-statuses.ts — all three
     * must be changed together (ProductStatusParityTest enforces the last two).
     *
     * @var list<string>
     */
    public const STATUSES = [
        'New',
        'Testing',
        'Scaling',
        'Maintaining',
        'Failed',
        'Inactive',
    ];

    protected $fillable = [
        'workspace_id',
        'owner_id',
        'title',
        'name',
        'code',
        'category',
        'status',
        'winning_date',
        'description',
    ];

    protected $casts = [
        // Y-m-d so the JSON handed to the edit datepicker / inventory list is a
        // plain calendar date, not an ISO timestamp.
        'winning_date' => 'date:Y-m-d',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Shops assigned to this product. This is the authoritative product link
     * (shops.product_id) — a product can be sold by many shops.
     */
    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    /**
     * Pages reachable through this product's shops. Kept as a convenience so
     * team-visibility scoping (whereHas('pages', ...)) and product.pages lookups
     * keep working now that the product link moved from the page to the shop.
     */
    public function pages(): HasManyThrough
    {
        return $this->hasManyThrough(Page::class, Shop::class, 'product_id', 'shop_id', 'id', 'id');
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
        return $query->selectSub(function ($q) {
            $q->from('ad_records')
                ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                ->join('pages', 'pages.id', '=', 'ads.page_id')
                ->join('shops', 'shops.id', '=', 'pages.shop_id')
                ->whereColumn('shops.product_id', 'products.id')
                ->selectRaw('COALESCE(SUM(ad_records.sales), 0)');
        }, 'advertising_sales');
    }

    public function scopeWithAdSpent(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        return $query->selectSub(function ($q) use ($start_date, $end_date) {
            $q->from('ad_records')
                ->join('ads', 'ads.id', '=', 'ad_records.ad_id')
                ->join('pages', 'pages.id', '=', 'ads.page_id')
                ->join('shops', 'shops.id', '=', 'pages.shop_id')
                ->whereColumn('shops.product_id', 'products.id')
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
                ->join('shops', 'shops.id', '=', 'pages.shop_id')
                ->whereColumn('shops.product_id', 'products.id')
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
            ->selectRaw('
            CASE
                WHEN COALESCE(p.ad_spent, 0) = 0 THEN 0
                ELSE COALESCE(p.sales, 0) / p.ad_spent
            END AS roas
        ');
    }

    public function scopeWithDeliveredCount(Builder $query, ?string $start_date = null, ?string $end_date = null): Builder
    {
        return $query->selectSub(function ($q) use ($start_date, $end_date) {
            $q->from('orders')
                ->join('pages', 'pages.id', '=', 'orders.page_id')
                ->join('shops', 'shops.id', '=', 'pages.shop_id')
                ->whereColumn('shops.product_id', 'products.id')
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
                ->join('shops', 'shops.id', '=', 'pages.shop_id')
                ->whereColumn('shops.product_id', 'products.id')
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
            ->selectRaw('
            COALESCE(
                p.returning_count / NULLIF((p.returning_count + p.delivered_count), 0),
                0
            ) AS rts
        ');
    }
}
