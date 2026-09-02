<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of numbers for a tested campaign or ad set. `date` is the calendar
 * day, `day` is which day of the test it is.
 */
class TestingDailyRecord extends Model
{
    protected $table = 'meta_ads_testing_daily_records';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'day' => 'integer',
        'sales' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        'roas' => 'decimal:2',
    ];

    public function testingItem(): BelongsTo
    {
        return $this->belongsTo(TestingItem::class, 'meta_ads_testing_item_id');
    }
}
