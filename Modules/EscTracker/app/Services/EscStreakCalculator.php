<?php

namespace Modules\EscTracker\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EscStreakCalculator
{
    /**
     * Recompute `current_streak` and `longest_streak` for a user from their ESC
     * records and persist them. Called whenever a record is created or updated.
     *
     * A streak is a run of consecutive calendar days that each have a record.
     * Because backfilling a missed day is allowed, we always recompute from the
     * full history rather than incrementing — filling yesterday's gap should
     * repair a broken streak, not just bump a counter.
     *
     * - `current_streak` counts back from today. If nothing is logged for today
     *   yet, the streak is still alive from yesterday (you have the rest of the
     *   day to log), so it still counts. A gap of two or more days resets it to 0.
     * - `longest_streak` is the longest such run anywhere in the history, and it
     *   never shrinks below the value already stored.
     */
    public function recalculateFor(User $user): void
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
            [$user->id],
        );

        if ($runs === []) {
            $user->forceFill(['current_streak' => 0])->save();

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

        $user->forceFill([
            'current_streak' => $currentStreak,
            'longest_streak' => max((int) $user->longest_streak, $longestRun),
        ])->save();
    }
}
