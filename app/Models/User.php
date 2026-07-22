<?php

namespace App\Models;

use App\Enums\Permission;
use BackedEnum;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_super_admin',
        'reminder_time',
        'current_streak',
        'longest_streak',
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
            'is_super_admin' => 'boolean',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
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
        return $this->is_super_admin;
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
     * Daily Extreme Self-Care records logged by this employee.
     */
    public function dailyEscRecords(): HasMany
    {
        return $this->hasMany(DailyEscRecord::class);
    }

    /**
     * This employee's ESC reminder settings (enabled, time, timezone, style).
     */
    public function escNotification(): HasOne
    {
        return $this->hasOne(EscNotification::class);
    }

    /**
     * Recompute `current_streak` and `longest_streak` from the employee's ESC
     * records and persist them. Called whenever a record is created or updated.
     *
     * A streak is a run of consecutive calendar days that each have a record.
     * Because backfilling a missed day is allowed, we always recompute from the
     * full history rather than incrementing — filling yesterday's gap should
     * repair a broken streak, not just bump a counter.
     *
     * - `current_streak` counts back from today. If nothing is logged for today
     *   yet, the streak is still alive from yesterday (you have the rest of the
     *   day to log), so we start counting there. A gap of two or more days
     *   resets it to 0.
     * - `longest_streak` is the longest such run anywhere in the history, and it
     *   never shrinks below the value already stored.
     */
    public function recalculateEscStreaks(): void
    {
        // Gaps-and-islands: subtracting a row number (in date order) from each
        // date collapses every run of consecutive days to a constant, so
        // grouping by it yields exactly one row per streak. This keeps the
        // consecutive-day maths in the database and returns only a handful of
        // rows, instead of shipping the user's entire history to PHP on every
        // save. Runs on the (user_id, record_date) unique index.
        $runs = DB::select(
            'select count(*) as length, max(record_date) as ends_on
               from (
                    select record_date,
                           date_sub(
                               record_date,
                               interval row_number() over (order by record_date) day
                           ) as streak_group
                      from daily_esc_records
                     where user_id = ?
               ) grouped
              group by streak_group',
            [$this->id],
        );

        if ($runs === []) {
            $this->forceFill(['current_streak' => 0])->save();

            return;
        }

        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $longestRun = 0;
        $currentStreak = 0;

        foreach ($runs as $run) {
            $length = (int) $run->length;
            $longestRun = max($longestRun, $length);

            // A streak is still "current" if it reaches today — or yesterday,
            // when today simply hasn't been logged yet. Only one run can match,
            // since a run touching both days would be a single run.
            $endsOn = Carbon::parse($run->ends_on)->toDateString();

            if ($endsOn === $today || $endsOn === $yesterday) {
                $currentStreak = $length;
            }
        }

        $this->forceFill([
            'current_streak' => $currentStreak,
            'longest_streak' => max((int) $this->longest_streak, $longestRun),
        ])->save();
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
     * Resolve the route name of the highest-priority dashboard this user can
     * access in the given workspace, or null if they can access none.
     *
     * Priority: Main → Sales & Marketing → Video Editor → CSR.
     *
     * Permission checks go through the gate (via `can`) so the production RBAC
     * bypass and owner/super-admin short-circuits are respected.
     */
    public function defaultDashboardRouteName(Workspace $workspace): ?string
    {
        $candidates = [
            ['workspace.dashboard', Permission::ViewMainDashboard],
            ['workspaces.sales-marketing.dashboard', Permission::ViewSalesMarketingDashboard],
            ['workspaces.video-editor.dashboard', Permission::ViewVideoEditorDashboard],
            ['workspaces.csr.dashboard', Permission::ViewCsrDashboard],
        ];

        foreach ($candidates as [$route, $permission]) {
            if ($this->can($permission->value, $workspace)) {
                return $route;
            }
        }

        // CSRs reach their dashboard through their role even without the
        // explicit permission (their role only carries CSR-category permissions).
        if ($workspace->csr_module_enabled && $this->isCsrOf($workspace)) {
            return 'workspaces.csr.dashboard';
        }

        return null;
    }
}
