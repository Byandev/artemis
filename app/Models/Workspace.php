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
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Models\Page as GencysPage;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryNotificationSetting;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Inventory\Models\PurchasedOrder;
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
        'max_shops',
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
        'gencys_module_enabled',
        'is_gencys_partner',
        'sales_marketing_dashboard_module_enabled',
        'video_editor_dashboard_module_enabled',
        'csr_dashboard_module_enabled',
        'sim_gateway_module_enabled',
        'ad_spend_goals_module_enabled',
        'inventory_sync',
        'public_password',
        'erp_username',
        'erp_password',
    ];

    protected $hidden = [
        'public_password',
        'erp_password',
    ];

    protected $appends = [
        'public_password_set',
        'erp_password_set',
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
        'gencys_module_enabled' => 'boolean',
        'is_gencys_partner' => 'boolean',
        'sales_marketing_dashboard_module_enabled' => 'boolean',
        'video_editor_dashboard_module_enabled' => 'boolean',
        'csr_dashboard_module_enabled' => 'boolean',
        'sim_gateway_module_enabled' => 'boolean',
        'ad_spend_goals_module_enabled' => 'boolean',
        'inventory_sync' => 'boolean',
        'max_shops' => 'integer',
        // Reversible encryption so the automation pipeline can read it back.
        'erp_password' => 'encrypted',
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
            $this->creatives_module_enabled ? null : 'Creatives',
            $this->meta_ads_module_enabled ? null : 'Meta Ads',
            $this->gencys_module_enabled ? null : 'Gencys ERP',
            $this->ad_spend_goals_module_enabled ? null : 'Ad Spend Goals',
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
            // RMO lives in the RTS category and the leaderboard in CSR, so each
            // toggle hides its own permissions rather than the whole category.
            $this->rmo_module_enabled ? null : PermissionEnum::ViewRmoManagement->value,
            $this->rmo_module_enabled ? null : PermissionEnum::ManageRmoSettings->value,
            $this->leaderboard_module_enabled ? null : PermissionEnum::ViewLeaderboards->value,
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

    /**
     * Whether ERP automation credentials are configured. Exposed to the client
     * without leaking the encrypted password itself.
     */
    public function getErpPasswordSetAttribute(): bool
    {
        return ! empty($this->attributes['erp_password']);
    }

    /** Verify a plaintext password against the stored public-pages password. */
    public function checkPublicPassword(string $password): bool
    {
        $hash = $this->attributes['public_password'] ?? null;

        return $hash !== null && Hash::check($password, $hash);
    }

    /**
     * A stable fingerprint of the current public-pages password hash, or null
     * when no password is set. Used to bind a session "unlocked" marker to the
     * exact password in effect, so changing/removing/re-adding the password
     * invalidates any prior unlock (bcrypt re-salts on every set).
     */
    public function publicPasswordFingerprint(): ?string
    {
        $hash = $this->attributes['public_password'] ?? null;

        return $hash ? sha1($hash) : null;
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
    public function addMember(User $user, $role = null): void
    {
        if (! $this->hasMember($user)) {
            $this->users()->attach($user->id, ['role_id' => $role]);
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

    /**
     * Assign (or clear, when null) a member's department within this workspace.
     */
    public function assignMemberDepartment(User $user, ?int $departmentId): int
    {
        return $this->users()->updateExistingPivot($user->id, [
            'department_id' => $departmentId,
        ]);
    }

    /**
     * Bulk assign (or clear, when null) the department for many members at once.
     *
     * @param  array<int>  $userIds
     */
    public function assignMembersDepartment(array $userIds, ?int $departmentId): int
    {
        return $this->users()
            ->newPivotStatement()
            ->where('workspace_id', $this->id)
            ->whereIn('user_id', $userIds)
            ->update(['department_id' => $departmentId]);
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

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
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

    public function rmoSetting()
    {
        return $this->hasOne(RmoSetting::class);
    }

    /**
     * Whether this workspace allows RMO orders from *any* past delivery date to
     * be assigned / re-statused. Off by default, in which case only today (and
     * the narrower yesterday carve-out) stays editable.
     */
    public function rmoEditPreviousDayEnabled(): bool
    {
        return (bool) $this->loadMissing('rmoSetting')->rmoSetting?->enable_edit_previous_day;
    }

    /**
     * Whether the RMO management page may re-status selected orders in one go.
     * Off by default — statuses are then changed one row at a time.
     */
    public function rmoBulkStatusUpdateEnabled(): bool
    {
        return (bool) $this->loadMissing('rmoSetting')->rmoSetting?->enable_bulk_status_update;
    }

    /**
     * Whether RMO statuses follow the courier's parcel status automatically —
     * a parcel that reports "delivered" re-tags its RMO row to "DELIVERED"
     * without a CSR touching it. Off by default.
     */
    public function rmoAutoTagStatusEnabled(): bool
    {
        return (bool) $this->loadMissing('rmoSetting')->rmoSetting?->enable_auto_tag_status;
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

    public function shopLimit(): ?int
    {
        return $this->max_shops ?? $this->subscription?->plan?->shop_limit;
    }

    public function shopLimitInfo(): array
    {
        $limit = $this->shopLimit();
        $count = $this->shops()->count();

        return [
            'limit' => $limit,
            'count' => $count,
            'reached' => $limit !== null && $count >= $limit,
        ];
    }

    public function hasReachedShopLimit(): bool
    {
        return $this->shopLimitInfo()['reached'];
    }

    public function inventoryItems(): HasMany|Workspace
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function interns(): HasMany|Workspace
    {
        return $this->hasMany(Intern::class);
    }

    public function gencysPages(): HasMany|Workspace
    {
        return $this->hasMany(GencysPage::class);
    }

    public function activeInterns(): HasMany|Workspace
    {
        return $this->hasMany(Intern::class)->where('active', true);
    }

    public function purchaseOrders(): HasMany|Workspace
    {
        return $this->hasMany(PurchasedOrder::class);
    }

    public function deliveredPurchaseOrders(): HasMany|Workspace
    {
        return $this->hasMany(PurchasedOrder::class)->where('status', 7);
    }

    public function closedPurchasedOrders(): HasMany|Workspace
    {
        return $this->hasMany(PurchasedOrder::class)->whereIn('status', PurchasedOrder::CLOSED_STATUSES);
    }

    public function inventoryNotificationSetting(): HasOne
    {
        return $this->hasOne(InventoryNotificationSetting::class);
    }
}
