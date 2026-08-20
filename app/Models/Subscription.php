<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    use HasFactory;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'workspace_id',
        'subscription_plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    /**
     * The date this subscription next falls due. A trial ends on
     * trial_ends_at; every other status renews at current_period_end. Null
     * when neither is set, in which case there is nothing to remind about.
     */
    public function dueDate(): ?Carbon
    {
        return $this->status === self::STATUS_TRIALING
            ? ($this->trial_ends_at ?? $this->current_period_end)
            : $this->current_period_end;
    }

    /**
     * Determine whether this subscription should gate access (show the
     * "subscription expired" modal / block API calls). A null subscription
     * is treated as lapsed by the callers.
     */
    public function isLapsed(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || $this->status === self::STATUS_CANCELED
            || $this->status === self::STATUS_PAST_DUE
            || ($this->status === self::STATUS_TRIALING && $this->trial_ends_at && $this->trial_ends_at->isPast())
            || ($this->status === self::STATUS_ACTIVE && $this->current_period_end && $this->current_period_end->isPast());
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
