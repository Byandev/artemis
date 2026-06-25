<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitorStatusRule extends Model
{
    protected $table = 'meta_ads_monitor_status_rules';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The three fixed statuses, in evaluation priority order: the first rule
     * (scaling, then maintain, then killed) whose conditions pass wins.
     */
    public const STATUSES = ['scaling', 'maintain', 'killed'];

    /**
     * Default rule per status, mirroring the creative-testing sheet's ROAS
     * bands (≥6 scaling, ≥3.5 maintain, <3.5 killed over the first 3 days).
     *
     * @var array<string, array<string, mixed>>
     */
    private const DEFAULTS = [
        'scaling' => ['metric' => 'roas', 'operator' => '>=', 'value' => 6, 'window' => 'first_3_days'],
        'maintain' => ['metric' => 'roas', 'operator' => '>=', 'value' => 3.5, 'window' => 'first_3_days'],
        'killed' => ['metric' => 'roas', 'operator' => '<', 'value' => 3.5, 'window' => 'first_3_days'],
    ];

    /**
     * Seed the three default status rules (with their starter condition) for a
     * workspace that has none yet. Idempotent — existing rules are left alone.
     */
    public static function ensureDefaultsFor(int $workspaceId): void
    {
        foreach (self::DEFAULTS as $status => $condition) {
            $rule = static::firstOrCreate(
                ['workspace_id' => $workspaceId, 'status' => $status],
                ['condition_operator' => 'and', 'is_active' => true],
            );

            if ($rule->wasRecentlyCreated) {
                $rule->conditions()->create($condition);
            }
        }
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(MonitorCondition::class, 'meta_ads_monitor_status_rule_id');
    }
}
