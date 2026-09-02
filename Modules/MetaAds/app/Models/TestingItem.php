<?php

namespace Modules\MetaAds\Models;

use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A campaign or ad set under test. `item_type` says which of the two, and
 * `item_id` is the Meta id — the primary key of meta_ads_campaigns /
 * meta_ads_sets — so `target()` resolves straight to the right model.
 */
class TestingItem extends Model
{
    protected $table = 'meta_ads_testing_items';

    protected $guarded = [];

    /**
     * The column default only applies on insert, so a model built in memory has
     * no source until it is read back. Spelling it out here means a freshly
     * created item is already a synced one to anything that inspects it.
     */
    protected $attributes = [
        'source' => 'meta',
    ];

    protected $casts = [
        'item_id' => 'string',
        'paused_at' => 'datetime',
        'start_date' => 'date',
    ];

    /** Entered by hand rather than pointing at a synced campaign / ad set. */
    public function getIsManualAttribute(): bool
    {
        return $this->source === 'manual';
    }

    /** Items that point at real Meta entities — the only ones a sync can touch. */
    public function scopeSynced(Builder $query): Builder
    {
        return $query->where('source', 'meta');
    }

    /** Whether this item is still accruing test days. */
    public function getIsPausedAttribute(): bool
    {
        return $this->paused_at !== null;
    }

    /** Items still under test — the ones a sync should touch. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('paused_at');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dailyRecords(): HasMany
    {
        return $this->hasMany(TestingDailyRecord::class, 'meta_ads_testing_item_id');
    }

    /**
     * The campaign or ad set this row tracks. Not a morph relation — the two
     * tables are keyed by Meta id, so the lookup is a plain find().
     */
    public function target(): Campaign|AdSet|null
    {
        return $this->item_type === 'campaign'
            ? Campaign::find($this->item_id)
            : AdSet::find($this->item_id);
    }
}
