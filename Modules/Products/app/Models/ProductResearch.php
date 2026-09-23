<?php

namespace Modules\Products\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A product development brief — what the lab is asked to make. Built in the
 * RDP Builder and filed on the RDPs list.
 *
 * The free-text fields hold one item per line, which is how they are typed and
 * how they read back into the textareas. Splitting them into rows would buy
 * nothing: nothing queries an individual claim.
 */
class ProductResearch extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** The packshot that was chosen or uploaded — what goes to the lab. */
    public const PACKSHOT_COLLECTION = 'PRODUCT_RESEARCH_PACKSHOT';

    /**
     * The options the generator produced, kept so the grid survives a reload
     * and so a rejected option can still be picked later.
     */
    public const PACKSHOT_OPTIONS_COLLECTION = 'PRODUCT_RESEARCH_PACKSHOT_OPTIONS';

    protected $table = 'product_research';

    protected $fillable = [
        'workspace_id',
        'created_by',
        'product_form_id',
        'target_market_id',
        'target_market_sub_id',
        'name',
        'positioning',
        'claims',
        'active_ingredients',
        'additional_instruction',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ProductForm::class, 'product_form_id');
    }

    /** The top-level target market — "Musculoskeletal". */
    public function targetMarket(): BelongsTo
    {
        return $this->belongsTo(TargetMarket::class, 'target_market_id');
    }

    /** The sub category under it — "Back Pain". This is what the list shows. */
    public function targetMarketSub(): BelongsTo
    {
        return $this->belongsTo(TargetMarket::class, 'target_market_sub_id');
    }

    public function registerMediaCollections(): void
    {
        // One packshot per brief: uploading or picking again replaces it rather
        // than leaving orphans in the bucket.
        $this->addMediaCollection(static::PACKSHOT_COLLECTION)
            ->singleFile()
            ->useDisk(config('filesystems.product_research_media_disk'));

        $this->addMediaCollection(static::PACKSHOT_OPTIONS_COLLECTION)
            ->useDisk(config('filesystems.product_research_media_disk'));
    }

    public function packshot(): ?Media
    {
        return $this->getFirstMedia(static::PACKSHOT_COLLECTION);
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }
}
