<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'owner_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
                    $workspace->slug = $originalSlug . '-' . $count;
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
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
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
        if (!$this->hasMember($user)) {
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
    public function updateMemberRole(User $user, string $role): void
    {
        $this->users()->updateExistingPivot($user->id, ['role' => $role]);
    }
}
