<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\IntegrationService;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\WelleDailyRecord;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * My ESC's stat cards — the signed-in user's own Welle figures.
 *
 * One endpoint per card, one method per endpoint: the page renders
 * immediately and each card skeletons on its own request rather than holding
 * the page behind the slowest count on it.
 *
 * Both gates the page itself applies are applied again here. An endpoint under
 * /api is reachable without going through the Inertia page, so the module
 * toggle and the grant have to be checked where the data is read, not only
 * where it is displayed. See WelleController for the page.
 *
 * Every rate on this page is counted over the days of the month that have
 * actually happened — the 1st through today, or the whole of a month already
 * past — so "7 of 16 days" means seven days out of sixteen lived rather than
 * seven out of a month that has not happened yet. Every card names the day
 * count it was drawn from, so a figure is never shown without the days behind
 * it.
 *
 * The denominator is the calendar rather than a count of rows, because a day
 * with none of the three pillars ticked has no row: it is a day that happened
 * and went unused, and leaving it out of the denominator would turn a month of
 * two good days into 100%.
 */
class WelleStatsController extends Controller
{
    use AuthorizesRequests;

    /** ESC Rate — the share of the month's elapsed days that were ESC days. */
    public function escRate(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $total = $this->elapsedDays($month);
        $esc = $this->days($request, $workspace, $month)->esc()->count();

        return response()->json([
            // Null rather than 0% for a month that has not started — a month
            // with no days lived in it yet is not a month of missed days.
            'value' => $total === 0 ? null : round($esc / $total * 100, 2),
            'esc_days' => $esc,
            'total_days' => $total,
            ...$this->context($request, $month),
        ]);
    }

    /**
     * Days with Movement — the days movement was ticked, whether or not the
     * other two pillars were, and what share of the month that is.
     *
     * A count rather than a rate, because it is the days themselves that are
     * the point; the share comes with it so thirteen days reads against the
     * month it sits in rather than in isolation. Meditation and Learning below
     * are the same card read off their own column.
     */
    public function movement(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $total = $this->elapsedDays($month);
        $days = $this->days($request, $workspace, $month)->where('movement', true)->count();

        return response()->json([
            // Null rather than a count of none, as the ESC rate above: with no
            // days lived yet there is nothing the pillar could have been missed
            // on.
            'value' => $total === 0 ? null : $days,
            'total_days' => $total,
            'rate' => $total === 0 ? null : round($days / $total * 100, 2),
            'pillar' => 'movement',
            ...$this->context($request, $month),
        ]);
    }

    /** Days with Meditation. */
    public function meditation(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $total = $this->elapsedDays($month);
        $days = $this->days($request, $workspace, $month)->where('meditation', true)->count();

        return response()->json([
            'value' => $total === 0 ? null : $days,
            'total_days' => $total,
            'rate' => $total === 0 ? null : round($days / $total * 100, 2),
            'pillar' => 'meditation',
            ...$this->context($request, $month),
        ]);
    }

    /** Days with Learning. */
    public function learning(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $total = $this->elapsedDays($month);
        $days = $this->days($request, $workspace, $month)->where('learning', true)->count();

        return response()->json([
            'value' => $total === 0 ? null : $days,
            'total_days' => $total,
            'rate' => $total === 0 ? null : round($days / $total * 100, 2),
            'pillar' => 'learning',
            ...$this->context($request, $month),
        ]);
    }

    /**
     * Pillar Breakdown — the three pillars side by side, each read over the
     * same month of days the cards are.
     *
     * One row per pillar rather than one card: the point of the chart is the
     * comparison, so the three counts have to come back together or two bars
     * could be drawn from different months.
     */
    public function pillarBreakdown(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $totals = $this->days($request, $workspace, $month)
            ->selectRaw('COALESCE(SUM(movement), 0) as movement_days')
            ->selectRaw('COALESCE(SUM(meditation), 0) as meditation_days')
            ->selectRaw('COALESCE(SUM(learning), 0) as learning_days')
            ->first();

        $total = $this->elapsedDays($month);
        $movement = (int) $totals->movement_days;
        $meditation = (int) $totals->meditation_days;
        $learning = (int) $totals->learning_days;

        return response()->json([
            'total_days' => $total,
            // A list rather than a map, in the order the bars are drawn — the
            // same order the Welle dashboard lists the pillars in.
            'pillars' => [
                [
                    'pillar' => 'movement',
                    'days' => $movement,
                    // Null rather than 0% for a month not started, as the cards do.
                    'rate' => $total === 0 ? null : round($movement / $total * 100, 2),
                ],
                [
                    'pillar' => 'meditation',
                    'days' => $meditation,
                    'rate' => $total === 0 ? null : round($meditation / $total * 100, 2),
                ],
                [
                    'pillar' => 'learning',
                    'days' => $learning,
                    'rate' => $total === 0 ? null : round($learning / $total * 100, 2),
                ],
            ],
            ...$this->context($request, $month),
        ]);
    }

    /**
     * The month's calendar — one entry per day Welle has a record of, with how
     * many of the three pillars were ticked on it.
     *
     * The rows themselves rather than a count: the calendar colours a day by
     * how complete it was, which is a figure no aggregate can give back. A day
     * with none of the three ticked has no row, as do days still to come and
     * days before the account was connected — the grid draws all of those as
     * blank, and `total_days` says how many of them were days that happened.
     */
    public function calendar(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $days = $this->days($request, $workspace, $month)
            ->orderBy('date')
            ->get(['date', 'pillars_completed'])
            ->map(fn (WelleDailyRecord $day) => [
                'date' => $day->date->toDateString(),
                'pillars_completed' => $day->pillars_completed,
            ]);

        return response()->json([
            'total_days' => $this->elapsedDays($month),
            'days' => $days,
            ...$this->context($request, $month),
        ]);
    }

    /**
     * The day-by-day log — every day of the month that has happened, with the
     * three pillars as they were ticked on it.
     *
     * The calendar above colours a day by how many pillars it carried; this
     * says which, which is the one question the grid cannot answer.
     *
     * Every elapsed day is here, the 1st through today, whether or not Welle
     * has a row for it: a day with none of the three ticked has no row, and
     * listing only the rows would leave the table skipping from the 3rd to the
     * 9th as though the days between had not happened. A day without a row is
     * sent with all three pillars false — which is what it was. Days still to
     * come are left out, as they are everywhere else on the page.
     */
    public function dailyLog(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $records = $this->days($request, $workspace, $month)
            ->get(['date', 'movement', 'meditation', 'learning', 'is_esc'])
            ->keyBy(fn (WelleDailyRecord $day) => $day->date->toDateString());

        $days = [];

        for ($offset = 0; $offset < $this->elapsedDays($month); $offset++) {
            $date = $month->addDays($offset)->toDateString();
            $record = $records->get($date);

            $days[] = [
                'date' => $date,
                'movement' => (bool) $record?->movement,
                'meditation' => (bool) $record?->meditation,
                'learning' => (bool) $record?->learning,
                'is_esc' => (bool) $record?->is_esc,
            ];
        }

        return response()->json([
            'total_days' => $this->elapsedDays($month),
            'days' => $days,
            ...$this->context($request, $month),
        ]);
    }

    /** The page's two gates, re-applied to the data behind it. */
    private function authorizeCards(Workspace $workspace): void
    {
        abort_unless($workspace->welle_module_enabled, 404);

        $this->authorize(Permission::ViewMyEsc->value, $workspace);
    }

    /**
     * How many days of the month have actually happened — the denominator
     * every rate on the page is drawn against.
     *
     * The whole of a month already past, the 1st through today for the current
     * one, and none of a month still to come. Read off the calendar rather than
     * counted from the table: days with nothing done have no row, and counting
     * rows would quietly drop them out of the denominator and flatter the rate.
     */
    private function elapsedDays(CarbonImmutable $month): int
    {
        $today = CarbonImmutable::today();

        if ($month->isAfter($today)) {
            return 0;
        }

        return (int) $month->diffInDays($month->endOfMonth()->min($today)) + 1;
    }

    /** The signed-in user's days in this workspace, for one month. */
    private function days(Request $request, Workspace $workspace, CarbonImmutable $month): Builder
    {
        return WelleDailyRecord::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->getKey())
            ->forMonth($month);
    }

    /**
     * What every card says about the figures beside them: which month they
     * cover, whether there is a Welle account behind the page at all, and
     * whether it has ever been fetched.
     *
     * The last two are what let a card tell three empty cards apart: no account
     * connected, an account connected but never fetched, and a month genuinely
     * spent doing nothing. The rows cannot answer that on their own — a day
     * with nothing ticked has no row, so "no rows" is the same silence whether
     * the month was missed or never synced.
     *
     * @return array<string, mixed>
     */
    private function context(Request $request, CarbonImmutable $month): array
    {
        $welle = $request->user()->integrationFor(IntegrationService::Welle);

        return [
            'month' => $month->format('Y-m'),
            'month_label' => $month->format('F Y'),
            'connected' => (bool) $welle?->hasToken(),
            'synced' => $welle?->last_synced_at !== null,
        ];
    }

    /**
     * The month being read — `?month=YYYY-MM`, this one by default.
     *
     * Anything unparseable falls back to the current month rather than 422ing:
     * the cards have no month picker yet, so a bad value can only come from a
     * hand-edited URL, and a card is better served by its default than by an
     * error.
     */
    private function month(Request $request): CarbonImmutable
    {
        $month = $request->query('month');

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            } catch (\Throwable) {
                // Falls through to the current month.
            }
        }

        return CarbonImmutable::today()->startOfMonth();
    }
}
