<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all workspaces the user belongs to.
     */
    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get workspaces owned by the user.
     */
    public function ownedWorkspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * Get all workspace invitations sent to this user's email.
     */
    public function workspaceInvitations()
    {
        return $this->hasMany(WorkspaceInvitation::class, 'email', 'email');
    }

    /**
     * Get pending workspace invitations for this user.
     */
    public function pendingWorkspaceInvitations()
    {
        return $this->workspaceInvitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Check if user is a member of a workspace.
     */
    public function isMemberOf(Workspace $workspace): bool
    {
        return $this->workspaces()->where('workspace_id', $workspace->id)->exists();
    }

    /**
     * Check if user is an admin or owner of a workspace.
     */
    public function isAdminOf(Workspace $workspace): bool
    {
        return $this->workspaces()
            ->where('workspace_id', $workspace->id)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    /**
     * Check if user owns a workspace.
     */
    public function ownsWorkspace(Workspace $workspace): bool
    {
        return $workspace->owner_id === $this->id;
    }
}
