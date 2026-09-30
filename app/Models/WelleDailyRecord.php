<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One day of a user's Welle ESC (Extreme Self Care) record.
 *
 * A day has three pillars — movement, meditation and learning — and counts as
 * ESC only when all three are done. That count and that verdict are derived
 * from the three flags and stored alongside them: every read of this table is
 * an aggregate over many rows, so they are worked out once here, on the one
 * path that writes a day, rather than recomputed on every card and rollup.
 *
 * A day with none of the three ticked is not written at all. A row here means
 * something was done, so the table cannot be counted to learn how many days
 * have elapsed — that comes from the calendar. See WelleStatsController.
 *
 * Welle credentials belong to the user, not the workspace, so the same day is
 * written once per Welle-enabled workspace the user belongs to — that keeps
 * every read workspace-scoped without asking Welle for the data twice.
 */
class WelleDailyRecord extends Model
{
    /** The three pillars, in the order the Welle dashboard lists them. */
    public const PILLARS = ['movement', 'meditation', 'learning'];

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'movement' => 'boolean',
        'meditation' => 'boolean',
        'learning' => 'boolean',
        'pillars_completed' => 'integer',
        'is_esc' => 'boolean',
        'synced_at' => 'datetime',
    ];

    /**
     * Upsert one day for a (workspace, user, date). Re-running a day corrects
     * the row rather than adding a second one.
     *
     * A day with nothing ticked is not stored, and null comes back. If such a
     * day already has a row — it was ticked when we last looked and has since
     * been un-ticked on Welle — the row is removed, so that "a row exists"
     * keeps meaning "something was done that day" after a correction too.
     *
     * @param  array<string, mixed>  $day  One entry of Welle's progress week.
     */
    public static function upsertDaily(int $workspaceId, int $userId, string $date, array $day): ?self
    {
        $pillars = [];

        foreach (self::PILLARS as $pillar) {
            $pillars[$pillar] = self::pillarDone($day, $pillar);
        }

        $key = [
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'date' => $date,
        ];

        $completed = count(array_filter($pillars));

        if ($completed === 0) {
            static::query()->where($key)->delete();

            return null;
        }

        return static::updateOrCreate($key, [
            ...$pillars,
            'pillars_completed' => $completed,
            // The ESC definition, in one place: all three pillars done.
            'is_esc' => $completed === count(self::PILLARS),
            'synced_at' => now(),
        ]);
    }

    /**
     * Whether a pillar was completed, tolerating the names an API of this shape
     * conventionally uses — a flat flag, a done/completed suffix, or a nested
     * object per pillar.
     *
     * @param  array<string, mixed>  $day
     */
    private static function pillarDone(array $day, string $pillar): bool
    {
        $candidates = [
            $pillar,
            "{$pillar}_done",
            "{$pillar}_completed",
            "has_{$pillar}",
            "is_{$pillar}",
            "pillars.{$pillar}",
            "{$pillar}.done",
            "{$pillar}.completed",
            "pillars.{$pillar}.done",
            "pillars.{$pillar}.completed",
        ];

        foreach ($candidates as $key) {
            $value = data_get($day, $key);

            if ($value === null || is_array($value)) {
                continue;
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
        }

        return false;
    }

    /** Days inside one calendar month, oldest first — the day-by-day list. */
    public function scopeForMonth(Builder $query, Carbon|string $month): Builder
    {
        $start = Carbon::parse($month)->startOfMonth();

        return $query
            ->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->orderBy('date');
    }

    /**
     * Only the days that counted as ESC.
     *
     * Reads the stored verdict rather than testing the three flags, so the
     * `user_id, is_esc, date` index answers it without touching the rows.
     */
    public function scopeEsc(Builder $query): Builder
    {
        return $query->where('is_esc', true);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
