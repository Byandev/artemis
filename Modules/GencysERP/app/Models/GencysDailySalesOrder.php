<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GencysDailySalesOrder extends Model
{
    protected $table = 'gencys_orders';

    // `id` holds Gencys' own order id (sent as "id" in the payload), so it is
    // assigned explicitly rather than auto-incremented.
    public $incrementing = false;

    /**
     * Ids at or above this are hand-made test orders, not real Gencys rows.
     * Gencys' own ids are 6-7 digits, so this range can't collide with them —
     * which keeps a re-sync from overwriting a test order and lets the UI tell
     * the two apart. Only the non-production test-order form mints these.
     */
    public const TEST_ID_BASE = 900000000;

    protected $appends = ['is_test'];

    protected $fillable = [
        'id',
        'workspace_id',
        'order_no',
        'order_date',
        'csr',
        'verifier_name',
        'upsell_by',
        'customer_name',
        'address',
        'province',
        'city',
        'brgy',
        'contact',
        'order_details',
        'total_qty',
        'price_final',
        'price_initial',
        'shipping_fee',
        'page',
        'platform',
        'tracking_number',
        'courier',
        'parcel_status',
        'order_status',
        'mop',
        'encoded_date',
        'parcel_updated_date',
        'shipped_out_date',
        'date_added',
        'price_upsell',
        'intern_brands_name',
        'total_cog',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'encoded_date' => 'datetime',
        'parcel_updated_date' => 'datetime',
        'shipped_out_date' => 'date:Y-m-d',
        'date_added' => 'datetime',
        'total_qty' => 'integer',
        'price_final' => 'decimal:2',
        'price_initial' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'price_upsell' => 'decimal:2',
        'total_cog' => 'decimal:2',
    ];

    /** SQL that normalizes order_details to a product name (strips "1x1X"/"2X" prefixes). */
    public const PRODUCT_EXPR = "UPPER(TRIM(REGEXP_REPLACE(order_details, '^[0-9]+[xX][0-9]*[xX]? *', '')))";

    /**
     * Distinct normalized product names for a workspace, most-sold first. Used to
     * suggest values when tagging a finance transaction (and to scope revenue on
     * the income statement). The sku→product chain is empty in the data, so the
     * normalized order_details text is the only working product key.
     *
     * @return array<int, string>
     */
    public static function distinctProducts(int $workspaceId, int $limit = 200): array
    {
        return static::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('order_details')
            ->where('order_details', '!=', '')
            ->selectRaw(self::PRODUCT_EXPR.' as product, COUNT(*) as orders')
            ->groupByRaw(self::PRODUCT_EXPR)
            ->orderByDesc('orders')
            ->limit($limit)
            ->pluck('product')
            ->filter(fn ($p) => filled($p))
            ->values()
            ->all();
    }

    /** True for hand-made test orders (see self::TEST_ID_BASE). */
    public function getIsTestAttribute(): bool
    {
        return $this->id !== null && $this->id >= self::TEST_ID_BASE;
    }

    /** Next free id in the test range. Call inside the creating transaction. */
    public static function nextTestId(): int
    {
        $max = (int) static::query()->where('id', '>=', self::TEST_ID_BASE)->max('id');

        return max($max + 1, self::TEST_ID_BASE);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GencysDailySalesOrderItem::class, 'order_id');
    }
}
