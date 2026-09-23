<?php

namespace Modules\Products\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A delivery format a product can take — Oil, Spray, Patch, Capsule. Used when
 * categorizing the catalog. Each form carries the sizes/variants it ships in,
 * and every one of those carries its own picture.
 */
class ProductForm extends Model
{
    /**
     * What each of the standard forms physically looks like, for the RDP
     * Builder's packshot step.
     *
     * Only a starting point: the description is editable per form, because a
     * workspace that packs its balm in a sachet rather than a tin needs to be
     * able to say so. Keyed on the lowercased name.
     *
     * @var array<string, string>
     */
    public const DEFAULT_PACKSHOT_DESCRIPTIONS = [
        'oil' => 'a small glass dropper bottle with a pipette cap',
        'spray' => 'a spray bottle with a fine-mist pump',
        'patch' => 'a single adhesive transdermal patch lying flat, skin-toned and slightly flexible, with its sealed foil sachet beside it',
        'inhaler' => 'a small handheld inhaler device',
        'cream' => 'an upright squeeze tube of cream with a flip cap',
        'balm' => 'a shallow round screw-top balm tin',
        'juice' => 'a sealed single-serve juice bottle',
        'coffee' => 'an upright stand-up foil pouch with a zip seal and flat bottom gusset',
        'gel' => 'an upright squeeze tube of gel with a flip cap',
        'seeds' => 'an upright stand-up pouch with a zip seal and flat bottom gusset',
    ];

    protected $fillable = [
        'workspace_id',
        'name',
        'packshot_description',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * How the packshot step should draw this form — what was typed on the
     * form, then the standard description for its name, then a plain fallback
     * that is vaguer but never wrong.
     */
    public function packshotDescription(): string
    {
        if (filled($this->packshot_description)) {
            return $this->packshot_description;
        }

        $key = mb_strtolower(trim((string) $this->name));

        return self::DEFAULT_PACKSHOT_DESCRIPTIONS[$key]
            ?? "the product packaged as a {$key}";
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductFormVariant::class)->orderBy('position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }
}
