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
 * ESC only when all three are done. Welle writes a row for every elapsed day,
 * including ones with nothing ticked, which is what lets "6 of 15 days" be
 * counted from this table without knowing today's date.
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
     * @param  array<string, mixed>  $day  One entry of Welle's progress week.
     */
    public static function upsertDaily(int $workspaceId, int $userId, string $date, array $day): self
    {
        $pillars = [];

        foreach (self::PILLARS as $pillar) {
            $pillars[$pillar] = self::pillarDone($day, $pillar);
        }

        $completed = count(array_filter($pillars));

        return static::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'date' => $date,
            ],
            [
                ...$pillars,
                'pillars_completed' => $completed,
                // The ESC definition, in one place: all three pillars done.
                'is_esc' => $completed === count(self::PILLARS),
                'synced_at' => now(),
            ],
        );
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

    /** Only the days that counted as ESC. */
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
