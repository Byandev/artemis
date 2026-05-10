<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insight extends Model
{
    protected $table = 'meta_ads_insights';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'spend' => 'decimal:4',
        'page_engagement_value' => 'decimal:4',
        'page_likes_value' => 'decimal:4',
        'photo_views_value' => 'decimal:4',
        'post_engagement_value' => 'decimal:4',
        'post_comments_value' => 'decimal:4',
        'post_shares_value' => 'decimal:4',
        'post_saves_value' => 'decimal:4',
        'post_reactions_value' => 'decimal:4',
        'messaging_first_replies_value' => 'decimal:4',
        'messaging_conversations_started_value' => 'decimal:4',
        'initiate_checkout_value' => 'decimal:4',
        'purchase_value' => 'decimal:4',
        'on_facebook_leads_value' => 'decimal:4',
        'lead_value' => 'decimal:4',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class, 'meta_ads_ad_id');
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'meta_ads_account_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'meta_ads_campaign_id');
    }

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class, 'meta_ads_set_id');
    }
}
