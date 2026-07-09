<?php

namespace App\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, ScopesToVisibleTeams, SoftDeletes;

    public $guarded = [];

    protected $casts = [
        'orders_last_synced_at' => 'datetime',
    ];

    protected $hidden = [];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }

    // SoftDeletes automatically excludes trashed records, so scopeActive is just an alias
    public function scopeActive(Builder $builder): Builder
    {
        return $builder->withoutTrashed();
    }

    public function scopeArchived(Builder $builder): Builder
    {
        return $builder->onlyTrashed();
    }

    public function isArchived(): bool
    {
        return $this->trashed();
    }

    public function archive(): void
    {
        $this->delete();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    public function customerServiceRepresentatives(): BelongsToMany
    {
        return $this->belongsToMany(CustomerServiceRepresentative::class, 'page_customer_service_representative');
    }

    /**
     * Team-level visibility flows through the page's shop: a user sees a page if
     * they share a team with its shop (see App\Models\Concerns\ScopesToVisibleTeams).
     */
    protected function visibilityTeamRelation(): string
    {
        return 'shop.teams';
    }

    public function latestBudget(): HasOne
    {
        return $this->hasOne(PageDailyBudgetRecord::class)->latestOfMany('date');
    }

    public function checklistCompletions(): MorphMany
    {
        return $this->morphMany(WorkspaceChecklistCompletion::class, 'target');
    }
}
