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
 * Every card is counted over the days Welle has a record of for the month, not
 * over the days on the calendar: FetchWelleProgress drops days still to come
 * and writes a row for every elapsed one, blank days included, so "7 of 16
 * days" means seven days out of sixteen lived rather than seven out of a month
 * that has not happened yet. Every card names the day count it was drawn from,
 * so a figure is never shown without the days behind it.
 */
class WelleStatsController extends Controller
{
    use AuthorizesRequests;

    /** ESC Rate — the share of the month's recorded days that were ESC days. */
    public function escRate(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $totals = $this->days($request, $workspace, $month)
            ->selectRaw('COUNT(*) as total_days, COALESCE(SUM(is_esc), 0) as esc_days')
            ->first();

        $total = (int) $totals->total_days;
        $esc = (int) $totals->esc_days;

        return response()->json([
            // Null rather than 0% with nothing recorded — a month that has not
            // been synced is not a month of missed days.
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

        $totals = $this->days($request, $workspace, $month)
            ->selectRaw('COUNT(*) as total_days, COALESCE(SUM(movement), 0) as pillar_days')
            ->first();

        $total = (int) $totals->total_days;
        $days = (int) $totals->pillar_days;

        return response()->json([
            // Null rather than a count of none: with no days recorded we do not
            // know the pillar was missed, only that Welle has not said.
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

        $totals = $this->days($request, $workspace, $month)
            ->selectRaw('COUNT(*) as total_days, COALESCE(SUM(meditation), 0) as pillar_days')
            ->first();

        $total = (int) $totals->total_days;
        $days = (int) $totals->pillar_days;

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

        $totals = $this->days($request, $workspace, $month)
            ->selectRaw('COUNT(*) as total_days, COALESCE(SUM(learning), 0) as pillar_days')
            ->first();

        $total = (int) $totals->total_days;
        $days = (int) $totals->pillar_days;

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
            ->selectRaw('COUNT(*) as total_days')
            ->selectRaw('COALESCE(SUM(movement), 0) as movement_days')
            ->selectRaw('COALESCE(SUM(meditation), 0) as meditation_days')
            ->selectRaw('COALESCE(SUM(learning), 0) as learning_days')
            ->first();

        $total = (int) $totals->total_days;
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
                    // Null rather than 0% with nothing recorded, as the cards do.
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
     * how complete it was, which is a figure no aggregate can give back. Days
     * still to come, and days before the account was connected, simply have no
     * row — the grid draws those as untracked rather than as empty days.
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
                'pillars_completed' => (int) $day->pillars_completed,
            ]);

        return response()->json([
            'total_days' => $days->count(),
            'days' => $days,
            ...$this->context($request, $month),
        ]);
    }

    /**
     * The day-by-day log — every day Welle has a record of, with the three
     * pillars as they were ticked on it.
     *
     * The calendar above colours a day by how many pillars it carried; this
     * says which, which is the one question the grid cannot answer. Same rows,
     * read one column further out.
     */
    public function dailyLog(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeCards($workspace);

        $month = $this->month($request);

        $days = $this->days($request, $workspace, $month)
            ->orderBy('date')
            ->get(['date', 'movement', 'meditation', 'learning', 'is_esc'])
            ->map(fn (WelleDailyRecord $day) => [
                'date' => $day->date->toDateString(),
                'movement' => (bool) $day->movement,
                'meditation' => (bool) $day->meditation,
                'learning' => (bool) $day->learning,
                'is_esc' => (bool) $day->is_esc,
            ]);

        return response()->json([
            'total_days' => $days->count(),
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
     * cover, and whether there is a Welle account behind the page at all.
     *
     * The last lets a card tell "connected, nothing synced yet" apart from "no
     * account connected", which are the same empty card with different advice.
     *
     * @return array<string, mixed>
     */
    private function context(Request $request, CarbonImmutable $month): array
    {
        return [
            'month' => $month->format('Y-m'),
            'month_label' => $month->format('F Y'),
            'connected' => (bool) $request->user()
                ->integrationFor(IntegrationService::Welle)
                ?->hasToken(),
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
