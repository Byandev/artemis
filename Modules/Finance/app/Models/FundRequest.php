<?php

namespace Modules\Finance\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundRequest extends Model
{
    protected $table = 'finance_request_funds';

    /**
     * The statuses a fund request can move through.
     */
    public const STATUSES = ['pending', 'approved', 'released', 'cancelled'];

    /** Statuses that represent a decision made by an approver. */
    public const APPROVED_STATUSES = ['approved', 'released'];

    /** A plain request. */
    public const TEMPLATE_BLANK = 'blank';

    /** Ad spend request: carries line items and derives its own amount. */
    public const TEMPLATE_AD_SPENT = 'ad_spent';

    public const TEMPLATES = [self::TEMPLATE_BLANK, self::TEMPLATE_AD_SPENT];

    protected $fillable = [
        'workspace_id',
        'template',
        'gotyme_number',
        'request_date',
        'reference_no',
        'requested_by',
        'purpose',
        'amount_requested',
        'date_needed',
        'approved_by',
        'status',
        'remarks',
    ];

    protected $casts = [
        'request_date' => 'date',
        'date_needed' => 'date',
        'amount_requested' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FundRequestItem::class, 'fund_request_id')->orderBy('sort_order');
    }

    public function isAdSpent(): bool
    {
        return $this->template === self::TEMPLATE_AD_SPENT;
    }

    /**
     * The users this request is charged to. Each carries an `amount` pivot —
     * their share of the request, the shares summing to the amount requested.
     */
    public function chargeToUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'finance_request_fund_charge_to', 'fund_request_id', 'user_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * The products this request covers, each with its share of the amount. Set
     * on every template, independently of the Ad Spent line items.
     */
    public function productShares(): HasMany
    {
        return $this->hasMany(FundRequestProduct::class, 'fund_request_id')->orderBy('sort_order');
    }

    /**
     * The transactions settling this request. A loose link: several entries may
     * point here and nothing reconciles their amounts against the request.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'fund_request_id');
    }

    /*
     * Relations are named so they do NOT collide with the same-named foreign-key
     * columns on serialization (e.g. a requestedBy() relation would serialize to
     * "requested_by" and overwrite the integer FK the forms rely on).
     */

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
