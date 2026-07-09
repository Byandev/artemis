<?php

namespace App\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shop extends Model
{
    use HasFactory, ScopesToVisibleTeams;

    public $guarded = [];

    protected $hidden = [
        'pos_token',
    ];

    protected $casts = [
        'orders_last_synced_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The product this shop sells. Assignment lives at the shop level (one
     * product per shop) — nullable while a shop is unassigned.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Teams this shop belongs to. Drives team-level visibility — a user sees a
     * shop (and its pages/orders) if they share any team with it.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_shop')->withTimestamps();
    }

    public function checklistCompletions(): MorphMany
    {
        return $this->morphMany(WorkspaceChecklistCompletion::class, 'target');
    }
}
