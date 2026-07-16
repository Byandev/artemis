<?php

namespace App\Queries;

use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\TeamAdSpendGoal;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes the live status of per-team daily ad-spend goals.
 *
 * A goal ("hit ₱500k/day") is judged daily, not accumulated. For each goal we
 * roll the unified advertiser_performance_daily_records up to the team PER DAY —
 * source-aware, exactly like AdvertiserDashboardQuery:
 *   - gencys:  advertiser is an Intern → gencys_interns.user_id → team_user → team
 *   - artemis: advertiser_id IS the user id → team_user → team
 * then report against the most recent complete day (yesterday) and the team's
 * peak day within the window.
 *
 * Note: a user who belongs to multiple teams contributes their spend to EACH of
 * their teams (the team_user pivot is many-to-many) — expected for per-team goals.
 */
class TeamAdSpendGoalStatusQuery
{
    /** Which unified source this workspace reads ('gencys' | 'artemis'). */
    private string $source;

    public function __construct(private readonly Workspace $workspace)
    {
        $this->source = $workspace->is_gencys_partner
            ? AdvertiserPerformanceDailyRecord::SOURCE_GENCYS
            : AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS;
    }

    /**
     * @param  Collection<int, TeamAdSpendGoal>  $goals
     * @return array<int, array<string, mixed>> goal id => status payload
     */
    public function statuses(Collection $goals): array
    {
        return $goals
            ->mapWithKeys(fn (TeamAdSpendGoal $goal) => [$goal->id => $this->statusFor($goal)])
            ->all();
    }

    /**
     * Status for one goal, judged against the team's per-day ad spend.
     *
     * @return array<string, mixed>
     */
    public function statusFor(TeamAdSpendGoal $goal): array
    {
        $target = (float) $goal->daily_target;
        $start = $goal->start_date->copy()->startOfDay();
        $end = $goal->end_date->copy()->startOfDay();
        $today = Carbon::today();

        // Only complete days count — today's spend is still coming in, so the
        // cutoff is min(yesterday, end).
        $yesterday = $today->copy()->subDay();
        $cutoff = $yesterday->lt($end) ? $yesterday : $end;

        // date string => team's total ad spend that day, within the window.
        $daily = $this->dailyTeamSpend($goal);

        $recentDate = null;
        $recentSpend = 0.0;
        $peakDate = null;
        $peakSpend = 0.0;
        $daysHit = 0;

        foreach ($daily as $date => $spend) {
            $d = Carbon::parse((string) $date);
            if ($d->gt($cutoff)) {
                continue; // skip today (incomplete) / anything past the cutoff
            }

            $spend = (float) $spend;

            if ($spend >= $target) {
                $daysHit++;
            }
            if ($peakDate === null || $spend > $peakSpend) {
                $peakSpend = $spend;
                $peakDate = (string) $date;
            }
            if ($recentDate === null || $d->gt(Carbon::parse($recentDate))) {
                $recentDate = (string) $date;
                $recentSpend = $spend;
            }
        }

        $hitRecent = $recentDate !== null && $recentSpend >= $target;
        $hitEver = $peakDate !== null && $peakSpend >= $target;

        // Pacing toward the DAILY target (no cumulative budget — the goal is a
        // per-day figure). days_remaining = today through the end date.
        $totalDays = (int) $start->diffInDays($end) + 1;
        $daysElapsed = $cutoff->lt($start) ? 0 : (int) $start->diffInDays($cutoff) + 1;
        $daysRemaining = max(0, $totalDays - $daysElapsed);

        // The "starting budget" — ad spend on the goal's start date (0 if that
        // day has no record). The increase-since-start is derived on the
        // frontend as recent_spend − starting_spend.
        $startingSpend = (float) ($daily[$goal->start_date->toDateString()] ?? 0);

        // Per-milestone progress. `reached` = the milestone was hit on any
        // complete day (peak ≥ amount); `reached_date` is the first such day.
        // The frontend derives "to go" / suggested increment from recent_spend.
        $completeDaily = [];
        foreach ($daily as $date => $sp) {
            if (! Carbon::parse((string) $date)->gt($cutoff)) {
                $completeDaily[(string) $date] = (float) $sp;
            }
        }
        ksort($completeDaily); // chronological (YYYY-MM-DD sorts lexically)

        $milestones = $goal->milestones
            ->map(function ($m) use ($completeDaily, $peakSpend) {
                $amount = (float) $m->amount;
                $reachedDate = null;
                foreach ($completeDaily as $date => $sp) {
                    if ($sp >= $amount) {
                        $reachedDate = $date;
                        break;
                    }
                }

                return [
                    'id' => $m->id,
                    'amount' => round($amount, 2),
                    'label' => $m->label,
                    'reached' => $peakSpend >= $amount,
                    'reached_date' => $reachedDate,
                ];
            })
            ->values()
            ->all();

        // Per-member progress: each member's slice of the target, plus their own
        // spend on the start date, the reference day (recent), and their best day.
        $memberDaily = $this->memberDailySpend($goal); // [user_id][date] => spend
        $startDateStr = $goal->start_date->toDateString();
        $members = $goal->members
            ->map(function ($gm) use ($memberDaily, $recentDate, $cutoff, $startDateStr) {
                $days = $memberDaily[$gm->user_id] ?? [];
                $recent = $recentDate !== null ? (float) ($days[$recentDate] ?? 0) : 0.0;
                $starting = (float) ($days[$startDateStr] ?? 0);

                $peak = 0.0;
                foreach ($days as $date => $sp) {
                    if (! Carbon::parse((string) $date)->gt($cutoff)) {
                        $peak = max($peak, (float) $sp);
                    }
                }

                $memberTarget = (float) $gm->daily_target;

                return [
                    'user_id' => $gm->user_id,
                    'name' => $gm->user?->name,
                    'daily_target' => round($memberTarget, 2),
                    'starting_spend' => round($starting, 2),
                    'recent_spend' => round($recent, 2),
                    'peak_spend' => round($peak, 2),
                    'reached' => $peak >= $memberTarget,
                ];
            })
            ->values()
            ->all();

        return [
            'daily_target' => $target,
            'start_date' => $goal->start_date->toDateString(),
            'end_date' => $goal->end_date->toDateString(),
            'total_days' => $totalDays,
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'days_hit' => $daysHit,
            // Most recent complete day (yesterday for an active goal, the last
            // in-window day for an ended one).
            'recent_date' => $recentDate,
            'recent_spend' => round($recentSpend, 2),
            'hit_recent' => $hitRecent,
            // Best single day the team reached within the window.
            'peak_date' => $peakDate,
            'peak_spend' => round($peakSpend, 2),
            'hit_ever' => $hitEver,
            'is_ended' => $end->lt($today),
            'status' => $this->deriveStatus($start, $today, $hitRecent, $hitEver),
            // Spend on the goal's start date (0 if unrecorded).
            'starting_spend' => round($startingSpend, 2),
            // Optional stepping-stone thresholds, ascending.
            'milestones' => $milestones,
            // Optional per-member target slices with their actual spend.
            'members' => $members,
        ];
    }

    /**
     * date string => the team's total ad spend that day, over the goal window.
     *
     * @return array<string, float|string>
     */
    private function dailyTeamSpend(TeamAdSpendGoal $goal): array
    {
        $query = DB::table('advertiser_performance_daily_records as apdr')
            ->where('apdr.workspace_id', $this->workspace->id)
            ->where('apdr.source', $this->source)
            ->whereBetween('apdr.date', [
                $goal->start_date->toDateString(),
                $goal->end_date->toDateString(),
            ]);

        // Gencys: advertiser is an Intern → its user_id → team_user.
        if ($this->source === AdvertiserPerformanceDailyRecord::SOURCE_GENCYS) {
            $query->join('gencys_interns as gi', function ($join) {
                $join->on('gi.id', '=', 'apdr.advertiser_id')
                    ->where('gi.workspace_id', '=', $this->workspace->id);
            })->join('team_user as tu', 'tu.user_id', '=', 'gi.user_id');
        } else {
            // Artemis: advertiser_id IS the user id → team_user directly.
            $query->join('team_user as tu', 'tu.user_id', '=', 'apdr.advertiser_id');
        }

        return $query
            ->where('tu.team_id', $goal->team_id)
            ->groupBy('apdr.date')
            ->selectRaw('apdr.date as date, SUM(COALESCE(apdr.ad_spent, 0)) as spend')
            ->pluck('spend', 'date')
            ->all();
    }

    /**
     * user id => (date => that member's ad spend), over the goal window. Same
     * source-aware linkage as the team roll-up, grouped by the member (user).
     *
     * @return array<int, array<string, float>>
     */
    private function memberDailySpend(TeamAdSpendGoal $goal): array
    {
        $query = DB::table('advertiser_performance_daily_records as apdr')
            ->where('apdr.workspace_id', $this->workspace->id)
            ->where('apdr.source', $this->source)
            ->whereBetween('apdr.date', [
                $goal->start_date->toDateString(),
                $goal->end_date->toDateString(),
            ]);

        if ($this->source === AdvertiserPerformanceDailyRecord::SOURCE_GENCYS) {
            $query->join('gencys_interns as gi', function ($join) {
                $join->on('gi.id', '=', 'apdr.advertiser_id')
                    ->where('gi.workspace_id', '=', $this->workspace->id);
            })
                ->join('team_user as tu', 'tu.user_id', '=', 'gi.user_id')
                ->where('tu.team_id', $goal->team_id)
                ->groupBy('gi.user_id', 'apdr.date')
                ->selectRaw('gi.user_id as user_id, apdr.date as date, SUM(COALESCE(apdr.ad_spent, 0)) as spend');
        } else {
            $query->join('team_user as tu', 'tu.user_id', '=', 'apdr.advertiser_id')
                ->where('tu.team_id', $goal->team_id)
                ->groupBy('apdr.advertiser_id', 'apdr.date')
                ->selectRaw('apdr.advertiser_id as user_id, apdr.date as date, SUM(COALESCE(apdr.ad_spent, 0)) as spend');
        }

        $map = [];
        foreach ($query->get() as $row) {
            $map[(int) $row->user_id][(string) $row->date] = (float) $row->spend;
        }

        return $map;
    }

    private function deriveStatus(Carbon $start, Carbon $today, bool $hitRecent, bool $hitEver): string
    {
        if ($start->gt($today)) {
            return 'upcoming';
        }
        if ($hitRecent) {
            return 'on_target';
        }
        if ($hitEver) {
            return 'slipping';
        }

        return 'below';
    }
}
