<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Inventory\Models\InventoryTransaction;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'owner_id',
        'show_inventory',
        'inventory_sync',
    ];

    protected $casts = [
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'show_inventory' => 'boolean',
        'inventory_sync' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = Str::slug($workspace->name);

                // Ensure slug is unique
                $originalSlug = $workspace->slug;
                $count = 1;
                while (static::where('slug', $workspace->slug)->exists()) {
                    $workspace->slug = $originalSlug.'-'.$count;
                    $count++;
                }
            }
        });
    }

    /**
     * Get the route key name for Laravel.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the owner of the workspace.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all users in the workspace.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withTimestamps()
            ->withPivot('role', 'role_id')
            ->using(WorkspaceUser::class);
    }

    /**
     * Get all members (non-owner users) in the workspace.
     */
    public function members(): BelongsToMany
    {
        return $this->users()->wherePivot('role', '!=', 'owner');
    }

    /**
     * Get all invitations for this workspace.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * Get pending invitations for this workspace.
     */
    public function pendingInvitations(): HasMany
    {
        return $this->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Check if a user is a member of this workspace.
     */
    public function hasMember(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if a user is an admin or owner of this workspace.
     */
    public function hasAdmin(User $user): bool
    {
        return $this->users()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    /**
     * Check if a user is the owner of this workspace.
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Add a user to the workspace.
     */
    public function addMember(User $user, string $role = 'member'): void
    {
        if (! $this->hasMember($user)) {
            $this->users()->attach($user->id, ['role' => $role]);
        }
    }

    /**
     * Remove a user from the workspace.
     */
    public function removeMember(User $user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Update a member's role.
     */
    public function updateMemberRole(User $user, int $roleId)
    {
        return $this->users()->updateExistingPivot($user->id, [
            'role_id' => $roleId,
        ]);
    }

    public function parcelJourneyNotificationTemplates(): HasMany
    {
        return $this->hasMany(ParcelJourneyNotificationTemplate::class);
    }

    public function facebookAccounts(): BelongsToMany
    {
        return $this->belongsToMany(FacebookAccount::class, 'workspace_facebook_account');
    }

    public function metrics(array $dateRange, array $filter): \App\Support\WorkspaceMetrics
    {
        return new \App\Support\WorkspaceMetrics($this, $dateRange, $filter);
    }

    public function shops(): HasMany|Workspace
    {
        return $this->hasMany(Shop::class);
    }

    public function pages(): HasMany|Workspace
    {
        return $this->hasMany(Page::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function isAdmin(User $user): bool
    {
        return $this->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function pageOwners()
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->has('pages')
            ->withTimestamps()
            ->withPivot('role', 'role_id')
            ->using(WorkspaceUser::class);
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'workspace_id');
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(WorkspaceApiKey::class);
    }
}
