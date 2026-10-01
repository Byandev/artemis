<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

class SyncRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RATE_LIMITED = 'rate_limited';

    public const ENTITY_AD_ACCOUNTS = 'ad_accounts';

    public const ENTITY_AD_ACCOUNT_PEOPLE = 'ad_account_people';

    public const ENTITY_CAMPAIGNS = 'campaigns';

    public const ENTITY_AD_SETS = 'ad_sets';

    public const ENTITY_ADS = 'ads';

    public const ENTITY_AD_CREATIVES = 'ad_creatives';

    public const ENTITY_AD_INSIGHTS = 'ad_insights';

    protected $table = 'meta_ads_sync_runs';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
        'records_synced' => 'integer',
    ];

    public static function start(string $entityType, ?string $scopeType = null, ?string $scopeId = null, array $meta = []): self
    {
        return self::create([
            'entity_type' => $entityType,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'status' => self::STATUS_RUNNING,
            'started_at' => Carbon::now(),
            'meta' => $meta ?: null,
        ]);
    }

    public function succeed(int $recordsSynced, array $meta = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUCCESS,
            'records_synced' => $recordsSynced,
            'finished_at' => Carbon::now(),
            'meta' => array_merge((array) $this->meta, $meta) ?: null,
        ])->save();
    }

    public function fail(Throwable|string $error, array $meta = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'error_message' => $error instanceof Throwable ? $error->getMessage() : $error,
            'meta' => array_merge((array) $this->meta, $meta) ?: null,
        ])->save();
    }

    public function markRateLimited(Throwable|string $error, int $retryAfterSeconds, array $meta = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_RATE_LIMITED,
            'finished_at' => Carbon::now(),
            'error_message' => $error instanceof Throwable ? $error->getMessage() : $error,
            'meta' => array_merge((array) $this->meta, $meta, ['retry_after_seconds' => $retryAfterSeconds]) ?: null,
        ])->save();
    }
}
