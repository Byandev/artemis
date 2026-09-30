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

    /**
     * What the Configure prompt dialog's "Reset to default" puts back.
     *
     * Only the voice and the constraints are editable. The rules that keep the
     * answer usable — one sentence of reasoning per name, varied angles, never
     * naming a delivery form the brief did not ask for — are appended by
     * ProductResearchNameSuggester and cannot be edited away, because the
     * "Pick a name" grid depends on them.
     */
    public const DEFAULT_PROMPT = 'You name over-the-counter health products for a Philippine direct-response brand. Names are 2–4 words, plain English, no trademarks, no medical claims of cure.';

    public const DEFAULT_COUNT = 10;

    /** The dialog's stepper stops here, and so does validation. */
    public const MAX_COUNT = 20;

    /**
     * The packshot default, worded as the design has it.
     *
     * Whether the render carries branding lives in this text — "a printed
     * label carrying the name" — rather than in a separate switch, so the
     * prompt is the single control. Edit it to ask for blank packaging if the
     * lettering ever comes back misspelled.
     */
    public const DEFAULT_PACKSHOT_PROMPT = 'Real studio product photograph on a plain neutral light grey seamless backdrop. Professional softbox lighting, crisp focus on the product, a printed label carrying the name, and a soft natural ground shadow.';

    public const DEFAULT_PACKSHOT_COUNT = 5;

    /** Each option is a paid image, so this ceiling is lower than the names'. */
    public const MAX_PACKSHOT_COUNT = 8;

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
        'naming_prompt',
        'name_count',
        'packshot_prompt',
        'packshot_count',
    ];

    protected $casts = [
        'name_count' => 'integer',
        'packshot_count' => 'integer',
    ];

    /** So an unsaved brief reports the same counts a stored one would. */
    protected $attributes = [
        'name_count' => self::DEFAULT_COUNT,
        'packshot_count' => self::DEFAULT_PACKSHOT_COUNT,
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

    /**
     * The editable half of the naming prompt, falling back to the default.
     *
     * Null is stored rather than a copy of the default, so improving the
     * default wording reaches every brief that never overrode it.
     */
    public function prompt(): string
    {
        return filled($this->naming_prompt) ? $this->naming_prompt : self::DEFAULT_PROMPT;
    }

    public function count(): int
    {
        return $this->name_count ?: self::DEFAULT_COUNT;
    }

    /** The editable half of the packshot prompt, falling back to the default. */
    public function packshotPrompt(): string
    {
        return filled($this->packshot_prompt) ? $this->packshot_prompt : self::DEFAULT_PACKSHOT_PROMPT;
    }

    public function packshotCount(): int
    {
        return $this->packshot_count ?: self::DEFAULT_PACKSHOT_COUNT;
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }
}
