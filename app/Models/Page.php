<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    public $guarded = [];

    protected $casts = [
        'orders_last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'botcake_token',
        'pancake_token',
        'infotxt_token',
        'pos_token'
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    public function customerServiceRepresentatives(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(CustomerServiceRepresentative::class, 'page_customer_service_representative');
    }
}
