<?php

namespace Modules\Pancake\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The last shipping-fee import per workspace, kept in the cache so the orders
 * page can report a queued import's progress and result without a table of its
 * own. One import at a time per workspace — a new upload replaces the record.
 */
class ShippingFeeImportStatus
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const FINISHED = 'finished';

    public const FAILED = 'failed';

    private const TTL = 86400;

    public static function key(int $workspaceId): string
    {
        return "pancake:shipping-fee-import:{$workspaceId}";
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(int $workspaceId): ?array
    {
        return Cache::get(self::key($workspaceId));
    }

    /** Start a fresh record, dropping the previous import's counts. */
    public static function begin(int $workspaceId, string $fileName): void
    {
        Cache::put(self::key($workspaceId), [
            'status' => self::QUEUED,
            'file' => $fileName,
            'queued_at' => now()->toIso8601String(),
        ], self::TTL);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function put(int $workspaceId, array $attributes): void
    {
        Cache::put(self::key($workspaceId), [
            ...(self::get($workspaceId) ?? []),
            ...$attributes,
        ], self::TTL);
    }

    public static function running(int $workspaceId): bool
    {
        return in_array(
            self::get($workspaceId)['status'] ?? null,
            [self::QUEUED, self::PROCESSING],
            true,
        );
    }
}
