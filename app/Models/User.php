<?php

namespace App\Models;

use App\Traits\LogsActivityForWorkspace;
use BackedEnum;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivityForWorkspace, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'role', 'is_super_admin'];

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
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Get all workspaces the user belongs to.
     */
    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role_id') // Ensure this matches your DB schema
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
     * Get all teams the user belongs to.
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user');
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
        if ($this->isSuperAdmin()) {
            return true;
        }

        return DB::table('workspace_user')
            ->where('user_id', $this->id)
            ->where('workspace_id', $workspace->id)
            ->exists();
    }

    /**
     * Check if user is an admin or owner of a workspace.
     */
    public function isAdminOf(Workspace $workspace): bool
    {
        return $this->workspaces()
            ->where('workspace_id', $workspace->id)
            ->whereIn('workspace_user.role', ['owner', 'admin'])
            ->exists();
    }

    /**
     * Check if user owns a workspace.
     */
    public function ownsWorkspace(Workspace $workspace): bool
    {
        return (int) $workspace->owner_id === (int) $this->id;
    }

    public function hasWorkspaceRole(Workspace $workspace, string $role): bool
    {
        return DB::table('workspace_user')
            ->join('roles', 'workspace_user.role_id', '=', 'roles.id')
            ->where('workspace_user.user_id', $this->id)
            ->where('workspace_user.workspace_id', $workspace->id)
            ->where('roles.name', $role)
            ->exists();
    }

    /**
     * Check if the user is a global Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    // public function hasReach(string $requiredRole): bool
    // {
    //     if ($this->isSuperAdmin()) {
    //         return true;
    //     }

    //     return $this->role === $requiredRole;
    // }

    public function pages(): User|HasMany
    {
        return $this->hasMany(Page::class, 'owner_id');
    }

    public function pancakeAccounts(): User|HasMany
    {
        return $this->hasMany(\Modules\Pancake\Models\User::class);
    }

    /**
     * Check if the user's role in the given workspace only has CSR-category permissions.
     * Owners, super admins, and users without a role are never considered CSR-only.
     */
    public function isCsrOf(Workspace $workspace): bool
    {
        if ($this->isSuperAdmin() || $this->ownsWorkspace($workspace)) {
            return false;
        }

        $roleId = DB::table('workspace_user')
            ->where('user_id', $this->id)
            ->where('workspace_id', $workspace->id)
            ->value('role_id');

        if (! $roleId) {
            return false;
        }

        $categories = DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('role_permissions.role_id', $roleId)
            ->pluck('permissions.category')
            ->unique();

        return $categories->isNotEmpty() && $categories->every(fn ($cat) => $cat === 'CSR');
    }

    /**
     * FIXED: This method now uses DB::table to avoid triggering the
     * Gate::before infinite loop which caused the 502/Timeout.
     */
    public function hasPermission(string|BackedEnum $permission, Workspace $workspace): bool
    {
        if ($this->isSuperAdmin() || $this->ownsWorkspace($workspace)) {
            return true;
        }

        $permissionName = $permission instanceof BackedEnum ? $permission->value : $permission;

        // Direct DB query to bypass Eloquent relations and events
        $roleId = DB::table('workspace_user')
            ->where('user_id', $this->id)
            ->where('workspace_id', $workspace->id)
            ->value('role_id');

        if (! $roleId) {
            return false;
        }

        return DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.name', $permissionName)
            ->exists();
    }
}
