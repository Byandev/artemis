<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasedOrder extends Model
{
    use ScopesToVisibleTeams;

    protected $table = 'inventory_purchased_orders';

    protected $fillable = [
        'workspace_id',
        'issue_date',
        'delivery_no',
        'expected_delivery_date',
        'cust_po_no',
        'control_no',
        'supplier',
        'delivery_fee',
        'total_amount',
        'status',
        'approved_at',
        'to_pay_at',
        'paid_at',
        'for_purchase_at',
        'purchased_at',
    ];

    protected $casts = [
        'issue_date' => 'date:Y-m-d',
        'expected_delivery_date' => 'date:Y-m-d',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => 'integer',
        // Datetimes, not dates: the ERP stamps these to the second, and the gap
        // between two stages is often minutes. Lists render them as dates.
        'approved_at' => 'datetime',
        'to_pay_at' => 'datetime',
        'paid_at' => 'datetime',
        'for_purchase_at' => 'datetime',
        'purchased_at' => 'datetime',
    ];

    /**
     * The standing delivery agreement: an order is due two weeks after it is
     * raised unless someone says otherwise.
     *
     * Stored rather than assumed at read time. The ERP sends an expected date on
     * barely any order, and a column that is null on 99% of rows forces every
     * reader to re-invent the same fallback — which is exactly how two readers
     * come to disagree about whether an order is late.
     */
    public const DEFAULT_DELIVERY_DAYS = 14;

    /**
     * The expected delivery date to store: whatever was supplied, else the
     * standing agreement measured from the issue date.
     *
     * Null only when there is no issue date to count from — an order nobody has
     * dated cannot be expected on any particular day.
     */
    public static function expectedDeliveryFor(mixed $supplied, mixed $issueDate): ?string
    {
        if ($supplied) {
            return CarbonImmutable::parse($supplied)->toDateString();
        }

        return $issueDate
            ? CarbonImmutable::parse($issueDate)->addDays(self::DEFAULT_DELIVERY_DAYS)->toDateString()
            : null;
    }

    /**
     * When the order reached each workflow stage, keyed by the field the ERP
     * sends it as, in workflow order.
     *
     * These are the ERP's own stamps. `paid_at` was previously derived from the
     * status trail and still falls back to it when the ERP omits the field —
     * see the public purchase-order sync.
     */
    public const STAGE_TIMESTAMPS = [
        'approved_at',
        'to_pay_at',
        'paid_at',
        'for_purchase_at',
        'purchased_at',
    ];

    /**
     * The status-trail labels each stage timestamp can be read off, keyed by
     * column, for the stages the ERP does not stamp itself.
     *
     * Matched on the label alone, case- and space-normalised: the label is the
     * stable part of a trail entry, while the free-text `detail` beside it is
     * whatever the person typed ("approved", "apprpved", "PAID-50%"). Approve
     * carries two spellings because the ERP has used both.
     */
    public const STAGE_LOG_LABELS = [
        'approved_at' => ['approve', 'approved'],
        'to_pay_at' => ['to pay'],
        'paid_at' => [PurchasedOrderStatusLog::PAID],
        'for_purchase_at' => ['for purchase'],
        'purchased_at' => ['purchased'],
    ];

    /**
     * Derived monitoring attributes appended to the serialized model. They read
     * the order's items, so eager-load `items.deliveries` before serializing.
     *
     * @var list<string>
     */
    protected $appends = [
        'fulfillment_status',
        'delivery_timeliness',
    ];

    /**
     * The procurement workflow stages. Mirrored on the frontend by
     * resources/js/constants/purchased-order-statuses.ts — keep the two in sync
     * (PurchasedOrderStatusParityTest enforces it).
     */
    public const STATUSES = [
        1 => 'For Approval',
        2 => 'Approved',
        3 => 'To Pay',
        4 => 'Paid',
        5 => 'For Purchase',
        6 => 'Waiting For Delivery',
        7 => 'Delivered',
        8 => 'Cancelled',
        9 => 'Manually Closed',
    ];

    public const DELIVERED = 7;

    public const CANCELLED = 8;

    /**
     * Manually closed at our end even though the source hasn't marked it
     * Delivered/Cancelled — a deliberate override to stop the order counting as
     * owed stock. Terminal, like Delivered/Cancelled.
     */
    public const MANUALLY_CLOSED = 9;

    /**
     * Terminal statuses — the order no longer owes stock, so its outstanding
     * quantities drop out of incoming-stock and reorder maths.
     * Exact complement of AWAITING_DELIVERY_STATUSES.
     */
    public const CLOSED_STATUSES = [self::DELIVERED, self::CANCELLED, self::MANUALLY_CLOSED];

    /**
     * Statuses that leave an order still owing stock — everything not yet
     * Delivered or Cancelled, including the pre-approval stages. Widened from
     * [4, 5, 6] in d03c4bd2 so an order counts toward incoming stock from the
     * moment it is raised, not only once paid.
     */
    public const AWAITING_DELIVERY_STATUSES = [1, 2, 3, 4, 5, 6];

    /**
     * Open orders that are the supplier's problem now — the only ones whose
     * quantities can honestly be called incoming stock.
     *
     * Payment is the commitment point: money has moved, the supplier is on the
     * hook for the goods, and the later stages are our own paperwork catching
     * up rather than anything that decides whether the stock arrives.
     *
     * Note this does NOT gate the reorder maths — that counts every open order,
     * because a raised PO is committed quantity and excluding it would trigger
     * a genuine double-order (see InventoryStockColumns::remainingAfterFulfillment).
     * The split exists so the flow panels can tell stock that is moving from
     * stock that is merely committed, and age the latter by how long it has sat.
     */
    public const RELEASED_STATUSES = [4, 5, 6];

    /**
     * Open orders still inside the business — raised, but not yet paid for.
     * Real intent, and worth showing, but not stock: nothing has been committed
     * that would make a supplier start. Exact complement of RELEASED_STATUSES
     * within AWAITING_DELIVERY_STATUSES.
     */
    public const REQUESTED_STATUSES = [1, 2, 3];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    /**
     * Team visibility fans out through the order's line items: the order is
     * visible if any of its items' inventory items reach the user's team.
     */
    protected function visibilityTeamRelation(): string
    {
        return 'items.inventoryItem.product.shops.teams';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchasedOrderItem::class, 'inventory_purchased_order_id');
    }

    /**
     * The ERP's audit trail for this order, oldest first. Synced wholesale —
     * see PurchasedOrderStatusLog.
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(PurchasedOrderStatusLog::class, 'inventory_purchased_order_id')
            ->orderBy('logged_at');
    }

    /** Aggregate fulfilment across the order's items: waiting | partial | delivered. */
    public function getFulfillmentStatusAttribute(): string
    {
        if (! $this->relationLoaded('items')) {
            return 'waiting';
        }

        $items = $this->items;

        if ($items->isEmpty() || $items->sum->delivered_qty <= 0) {
            return 'waiting';
        }

        return $items->every(fn ($item) => $item->balance <= 0) ? 'delivered' : 'partial';
    }

    /**
     * Schedule status against the order's expected delivery date: ontime | delayed | null.
     * Null only when no expected date is set. An order that has been fully delivered
     * is settled and never goes on to become delayed; anything still owing stock is
     * delayed once the expected date has passed.
     *
     * Note this measures the order against its deadline as of today, not when each
     * delivery actually landed — a fully-delivered order reads "ontime" even if the
     * final delivery was late.
     */
    public function getDeliveryTimelinessAttribute(): ?string
    {
        $expected = $this->expected_delivery_date;

        if (! $expected) {
            return null;
        }

        if ($this->fulfillment_status === 'delivered') {
            return 'ontime';
        }

        return now()->startOfDay()->gt($expected) ? 'delayed' : 'ontime';
    }

    /**
     * Re-derive `paid_at` from the order's status trail and persist it.
     *
     * The earliest Paid entry wins: an order marked paid, reverted and paid
     * again was first settled on the first date, and a 50% payment is still the
     * date money moved. Null when the trail carries no Paid entry — including
     * when the ERP has withdrawn one.
     *
     * Written with a bare update so it neither touches `updated_at` nor fires
     * model events: this is a derived column catching up with its source, not a
     * change to the order.
     */
    public function recalculatePaidAt(): void
    {
        $this->recalculateStageTimestamps(['paid_at']);
    }

    /**
     * Read the stage timestamps back off the status trail.
     *
     * The ERP used to stamp these on the order itself; the report the sync now
     * scrapes carries only the trail, so the columns are derived from it — the
     * earliest entry for a stage is when the order reached it. $only limits the
     * work to particular columns (null does the lot, an empty array none), so a
     * caller can derive exactly the stages the ERP did not send explicitly and
     * leave the rest alone.
     *
     * Written with a bare update so it neither touches `updated_at` nor fires
     * model events: these are derived columns catching up with their source,
     * not a change to the order.
     *
     * @param  array<int, string>|null  $only
     */
    public function recalculateStageTimestamps(?array $only = null): void
    {
        if ($only === []) {
            return;
        }

        $reached = $this->statusLogs()
            // The relation sorts the trail for reading; grouping it here is a
            // different question, and only_full_group_by rejects the leftover
            // ORDER BY.
            ->reorder()
            ->whereNotNull('logged_at')
            ->selectRaw('LOWER(TRIM(status)) as label, MIN(logged_at) as reached_at')
            ->groupBy('label')
            ->pluck('reached_at', 'label');

        $updates = [];

        foreach (self::STAGE_LOG_LABELS as $column => $labels) {
            if ($only !== null && ! in_array($column, $only, true)) {
                continue;
            }

            // Whichever spelling of the stage came first; null once the ERP has
            // withdrawn the entry the column was standing on.
            $reachedAt = collect($labels)
                ->map(fn (string $label) => $reached[$label] ?? null)
                ->filter()
                ->min();

            if ($this->{$column}?->toDateTimeString() !== $reachedAt) {
                $updates[$column] = $reachedAt;
            }
        }

        if (! $updates) {
            return;
        }

        static::withoutTimestamps(fn () => static::whereKey($this->getKey())->toBase()->update($updates));

        foreach ($updates as $column => $value) {
            $this->{$column} = $value;
            $this->syncOriginalAttribute($column);
        }
    }

    /** Total quantity still owed across the order's line items. Requires `items.deliveries`. */
    public function outstandingBalance(): int
    {
        $this->loadMissing('items.deliveries');

        return (int) $this->items->sum(fn (PurchasedOrderItem $item) => $item->balance);
    }
}
