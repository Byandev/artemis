<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Jobs\MarkOptimizationRunStep;

/**
 * One end-to-end "sync today's data, then evaluate the due rules" run for a
 * workspace. Tracks which phase the run is currently on so the UI can show
 * live progress (refresh accounts → campaigns → ad sets → ads → insights →
 * evaluate). Advanced by {@see MarkOptimizationRunStep}
 * marker jobs interleaved into the chain.
 */
class OptimizationRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STEP_AD_ACCOUNTS = 'ad_accounts';

    public const STEP_CAMPAIGNS = 'campaigns';

    public const STEP_AD_SETS = 'ad_sets';

    public const STEP_ADS = 'ads';

    public const STEP_INSIGHTS = 'insights';

    public const STEP_EVALUATE = 'evaluate';

    /**
     * Display label for each phase. Order here is the canonical run order.
     */
    public const STEP_LABELS = [
        self::STEP_AD_ACCOUNTS => 'Refresh ad accounts',
        self::STEP_CAMPAIGNS => 'Campaigns',
        self::STEP_AD_SETS => 'Ad sets',
        self::STEP_ADS => 'Ads',
        self::STEP_INSIGHTS => 'Insights (today)',
        self::STEP_EVALUATE => 'Evaluate rules',
    ];

    protected $table = 'meta_ads_optimization_runs';

    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
        'total_rules' => 'integer',
        'total_accounts' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Build the ordered step plan ([{key, label}, ...]) for the given keys.
     *
     * @param  array<int, string>  $stepKeys
     * @return array<int, array{key: string, label: string}>
     */
    public static function planFor(array $stepKeys): array
    {
        return array_map(
            fn (string $key) => ['key' => $key, 'label' => self::STEP_LABELS[$key] ?? $key],
            $stepKeys,
        );
    }

    /**
     * Move the run onto the given phase. A finished run never reopens, so a
     * late marker (e.g. from a retried job) is a no-op.
     */
    public function markStep(string $step): void
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_RUNNING,
            'current_step' => $step,
        ])->save();
    }

    public function markCompleted(): void
    {
        $this->forceFill([
            'status' => self::STATUS_COMPLETED,
            'current_step' => null,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Mark the run failed, keeping current_step so the UI can show which phase
     * broke. A run that already completed is left untouched.
     */
    public function markFailed(?string $error = null): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'error_message' => $error,
        ])->save();
    }
}
