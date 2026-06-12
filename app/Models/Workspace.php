<?php

namespace App\Models;

use App\Enums\Permission as PermissionEnum;
use App\Support\Metrics\MetricRegistry;
use App\Support\WorkspaceMetrics;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\MetaAds\Models\User as MetaUser;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'owner_id',
        'monthly_order_volume',
        'max_pages',
        'inventory_module_enabled',
        'finance_module_enabled',
        'products_module_enabled',
        'teams_module_enabled',
        'checklist_module_enabled',
        'csr_module_enabled',
        'rmo_module_enabled',
        'leaderboard_module_enabled',
        'botcake_module_enabled',
        'creatives_module_enabled',
        'meta_ads_module_enabled',
        'sales_marketing_dashboard_module_enabled',
        'video_editor_dashboard_module_enabled',
        'csr_dashboard_module_enabled',
        'inventory_sync',
        'public_password',
    ];

    protected $hidden = [
        'public_password',
    ];

    protected $appends = [
        'public_password_set',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'inventory_module_enabled' => 'boolean',
        'finance_module_enabled' => 'boolean',
        'products_module_enabled' => 'boolean',
        'teams_module_enabled' => 'boolean',
        'checklist_module_enabled' => 'boolean',
        'csr_module_enabled' => 'boolean',
        'rmo_module_enabled' => 'boolean',
        'leaderboard_module_enabled' => 'boolean',
        'botcake_module_enabled' => 'boolean',
        'creatives_module_enabled' => 'boolean',
        'meta_ads_module_enabled' => 'boolean',
        'sales_marketing_dashboard_module_enabled' => 'boolean',
        'video_editor_dashboard_module_enabled' => 'boolean',
        'csr_dashboard_module_enabled' => 'boolean',
        'inventory_sync' => 'boolean',
        'max_pages' => 'integer',
    ];

    /**
     * Permission categories that should be hidden from the role editor and
     * stripped from a user's effective permissions when their owning module is
     * off. Single source of truth shared by the role editor and the Inertia
     * permission share so the two can't drift.
     *
     * @return array<int, string>
     */
    public function disabledPermissionCategories(): array
    {
        return array_values(array_filter([
            $this->finance_module_enabled ? null : 'Finance',
            $this->inventory_module_enabled ? null : 'Inventory',
            $this->products_module_enabled ? null : 'Products',
            $this->teams_module_enabled ? null : 'Teams',
            $this->checklist_module_enabled ? null : 'Checklist',
            $this->csr_module_enabled ? null : 'CSR',
            $this->botcake_module_enabled ? null : 'Botcake',
            $this->meta_ads_module_enabled ? null : 'Meta Ads',
        ]));
    }

    /**
     * Individual permission names that should be hidden from the role editor and
     * stripped from a user's effective permissions when their owning toggle is
     * off. Use this for partial-category hides (e.g. the S&M, Video Editor, and
     * CSR dashboard toggles each hide one "Dashboards" permission, not the whole
     * category).
     *
     * @return array<int, string>
     */
    public function hiddenPermissionNames(): array
    {
        return array_values(array_filter([
            $this->sales_marketing_dashboard_module_enabled ? null : PermissionEnum::ViewSalesMarketingDashboard->value,
            $this->video_editor_dashboard_module_enabled ? null : PermissionEnum::ViewVideoEditorDashboard->value,
            $this->csr_dashboard_module_enabled ? null : PermissionEnum::ViewCsrDashboard->value,
        ]));
    }

    /**
     * Whether a public-pages access password is configured (gates the public
     * RMO management and leaderboard pages). Exposed without leaking the hash.
     */
    public function getPublicPasswordSetAttribute(): bool
    {
        return ! empty($this->attributes['public_password']);
    }

    /** Verify a plaintext password against the stored public-pages password. */
    public function checkPublicPassword(string $password): bool
    {
        $hash = $this->attributes['public_password'] ?? null;

        return $hash !== null && Hash::check($password, $hash);
    }

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

        static::created(function (Workspace $workspace) {
            $defaults = MetricRegistry::defaults();

            $workspace->metricSetting()->firstOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'allowed_metrics' => $defaults,
                    'default_metrics' => $defaults,
                ]
            );
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
            ->leftJoin('roles', 'workspace_user.role_id', '=', 'roles.id')
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query
                    ->whereIn('workspace_user.role', ['owner', 'admin'])
                    ->orWhere('roles.name', 'admin');
            })
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

    public function metaUsers(): BelongsToMany
    {
        return $this->belongsToMany(MetaUser::class, 'meta_ads_workspace_user', 'workspace_id', 'meta_ads_user_id')
            ->withPivot('connected_by_user_id')
            ->withTimestamps();
    }

    public function metrics(array $dateRange, array $filter, string $source = 'live'): WorkspaceMetrics
    {
        return new WorkspaceMetrics($this, $dateRange, $filter, $source);
    }

    public function shops(): HasMany|Workspace
    {
        return $this->hasMany(Shop::class);
    }

    public function pages(): HasMany|Workspace
    {
        return $this->hasMany(Page::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function isAdmin(User $user): bool
    {
        return $this->users()
            ->leftJoin('roles', 'workspace_user.role_id', '=', 'roles.id')
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query
                    ->where('workspace_user.role', 'admin')
                    ->orWhere('roles.name', 'admin');
            })
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

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(WorkspaceChecklist::class, 'workspace_id');
    }

    public function metricSetting()
    {
        return $this->hasOne(WorkspaceMetricSetting::class);
    }

    public function allowedMetrics(): array
    {
        return $this->metricSetting
            ? ($this->metricSetting->allowed_metrics ?? [])
            : MetricRegistry::defaults();
    }

    public function defaultMetrics(): array
    {
        $allowed = $this->allowedMetrics();
        $defaults = $this->metricSetting?->default_metrics ?? [];

        if ($this->metricSetting) {
            return array_values(array_intersect($defaults, $allowed));
        }

        return array_values(array_intersect(MetricRegistry::defaults(), $allowed));
    }

    public function getMetricSettings(): array
    {
        return [
            'allowed' => $this->allowedMetrics(),
            'defaults' => $this->defaultMetrics(),
        ];
    }

    public function pageLimit(): ?int
    {
        return $this->max_pages ?? $this->subscription?->plan?->page_limit;
    }

    public function pageLimitInfo(): array
    {
        $limit = $this->pageLimit();
        $count = $this->pages()->count();

        return [
            'limit' => $limit,
            'count' => $count,
            'reached' => $limit !== null && $count >= $limit,
        ];
    }

    public function hasReachedPageLimit(): bool
    {
        return $this->pageLimitInfo()['reached'];
    }
}
