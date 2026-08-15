<?php

namespace Modules\Inventory\Support;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Whether a workspace's upstream syncs have landed cleanly enough to freeze.
 *
 * The snapshot is not a report any more — it is what the items list and the
 * dashboard read. Freezing it while an ERP sync is still running, or has failed,
 * records a half-arrived day as though it were the truth, and nothing
 * afterwards distinguishes it from a complete one. A missing day is obvious and
 * self-correcting on the next run; a silently short one is neither.
 *
 * The windows differ because the feeds do. Sales orders are the demand behind
 * every average, and they arrive across several runs a day, so a failure any
 * time in the last three days still poisons a 3-day average. Transactions and
 * purchase orders are today's stock and today's commitments — yesterday's are
 * already in, so only today matters.
 */
class SnapshotReadiness
{
    /**
     * Sync types that must be clean, and how far back to look, in days.
     * Zero means today only.
     */
    private const REQUIRED = [
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER => 3,
        GencysSyncRun::TYPE_TRANSACTION_HISTORY => 0,
        GencysSyncRun::TYPE_PURCHASE_ORDER => 0,
    ];

    /** A run in either of these states has not delivered its data. */
    private const UNFINISHED = [GencysSyncRun::STATUS_PENDING, GencysSyncRun::STATUS_FAILED];

    /**
     * Why this workspace should not be frozen right now, in plain words.
     * Empty means go ahead.
     *
     * @return list<string>
     */
    public static function blockers(Workspace $workspace): array
    {
        $counts = DB::table('gencys_sync_runs')
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', self::UNFINISHED)
            ->whereIn('sync_type', array_keys(self::REQUIRED))
            ->where(function ($q) {
                foreach (self::REQUIRED as $type => $days) {
                    $q->orWhere(fn ($w) => $w
                        ->where('sync_type', $type)
                        ->where('started_at', '>=', self::since($days)));
                }
            })
            ->groupBy('sync_type', 'status')
            ->selectRaw('sync_type, status, COUNT(*) as runs')
            ->get();

        $blockers = [];

        foreach (self::REQUIRED as $type => $days) {
            $rows = $counts->where('sync_type', $type);

            if ($rows->isEmpty()) {
                continue;
            }

            $detail = $rows->map(fn ($r) => "{$r->runs} {$r->status}")->implode(', ');
            $window = $days === 0 ? 'today' : "the last {$days} days";

            $blockers[] = str_replace('_', ' ', $type)." — {$detail} in {$window}";
        }

        return $blockers;
    }

    /** The start of the window for a lookback of $days (0 = today). */
    private static function since(int $days): Carbon
    {
        return Carbon::today()->subDays($days);
    }
}
