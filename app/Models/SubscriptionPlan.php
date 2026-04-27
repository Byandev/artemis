<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    public const CODE_FREE_TRIAL = 'free_trial';

    public const CODE_STARTER = 'starter';

    public const CODE_GROWTH = 'growth';

    public const CODE_SCALE = 'scale';

    public const ANALYTICS_BASIC = 'basic';

    public const ANALYTICS_FULL = 'full';

    public const SUPPORT_CHAT = 'chat';

    public const SUPPORT_PRIORITY_CHAT = 'priority_chat';

    public const SUPPORT_DEDICATED = 'dedicated';

    protected $fillable = [
        'code',
        'name',
        'price_php',
        'order_limit',
        'page_limit',
        'data_retention_months',
        'analytics_tier',
        'parcel_journey_rate_php',
        'parcel_journey_sms_enabled',
        'support_tier',
        'trial_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_php' => 'decimal:2',
        'parcel_journey_rate_php' => 'decimal:2',
        'order_limit' => 'integer',
        'page_limit' => 'integer',
        'data_retention_months' => 'integer',
        'trial_days' => 'integer',
        'parcel_journey_sms_enabled' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
