<?php

namespace App\Queries;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Pancake\Models\PageOrdersToLocationDailyRecord;

/**
 * Reads the RTS heat map off page_orders_to_location_daily_records.
 *
 * The rollup already carries the resolved GADM ids and one row per page / day /
 * destination, so shading the whole country is a single indexed aggregation
 * rather than the orders → shipping_addresses join the other RTS queries run.
 *
 * Provinces are grouped by GADM id rather than by name, so the handful of
 * provinces that share a polygon (Davao del Sur and Davao Occidental, split after
 * GADM drew its boundaries) come back as the one area the map can actually draw.
 *
 * Region is the coarser grain — the island group off Pancake's own province list,
 * denormalised onto every row at build time.
 */
class RtsHeatMapQuery
{
    /**
     * RTS rate is value-based — returned money over money that reached an outcome
     * — the same formula page_daily_records uses. Rating by order count treats a
     * ₱200 return as the equal of a ₱5,000 one, which is not how the loss lands.
     */
    private const METRICS_SQL = '
        SUM(orders) AS orders,
        SUM(sales) AS sales,
        SUM(delivered_count) AS delivered_count,
        SUM(delivered_amount) AS delivered_amount,
        SUM(returning_count) AS returning_count,
        SUM(returning_amount) AS returning_amount,
        ROUND(
            (SUM(returning_amount) * 100.0) /
            NULLIF(SUM(returning_amount) + SUM(delivered_amount), 0),
            2
        ) AS rts_rate_percentage
    ';

    public function __construct(
        private readonly Workspace $workspace,
        private readonly Request $request,
    ) {}

    /**
     * Areas that resolved to somewhere on the map. Region rows key on the island
     * group, province rows on their GADM id.
     */
    public function mapped(string $groupBy): Collection
    {
        if ($groupBy === 'region') {
            return $this->base()
                ->whereNotNull('region')
                ->selectRaw('region, '.self::METRICS_SQL)
                ->groupBy('region')
                ->get();
        }

        return $this->base()
            ->whereNotNull('gadm_province_gid')
            ->selectRaw('gadm_province_gid AS gid, MIN(province_id) AS province_id, '.
                'MIN(province_name) AS province_name, '.self::METRICS_SQL)
            ->groupBy('gadm_province_gid')
            ->get();
    }

    /**
     * Areas that could not be placed — in practice an order whose address carries
     * no province at all. Grouped by name so the page can say what is missing.
     */
    public function unmapped(string $groupBy): Collection
    {
        $column = $groupBy === 'region' ? 'region' : 'gadm_province_gid';

        return $this->base()
            ->whereNull($column)
            ->selectRaw('province_name, '.self::METRICS_SQL)
            ->groupBy('province_name')
            ->get();
    }

    /**
     * The span the rollup actually covers for this workspace, ignoring the
     * selected dates. An empty map is nearly always a date range with nothing in
     * it — usually because the rollup has not been built that far forward — so the
     * page can say so instead of just showing blank country.
     *
     * @return array{first: string|null, last: string|null}
     */
    public function availableRange(): array
    {
        $row = $this->base(withDates: false)
            ->reorder()
            ->selectRaw('MIN(date) AS first_date, MAX(date) AS last_date')
            ->first();

        return [
            'first' => $row?->first_date ? substr((string) $row->first_date, 0, 10) : null,
            'last' => $row?->last_date ? substr((string) $row->last_date, 0, 10) : null,
        ];
    }

    private function base(bool $withDates = true): Builder
    {
        $query = PageOrdersToLocationDailyRecord::query()
            ->where('workspace_id', $this->workspace->id);

        if ($user = $this->request->user()) {
            $query->visibleTo($user, $this->workspace);
        }

        $start = $this->request->input('start_date');
        $end = $this->request->input('end_date');

        if ($withDates && $start && $end) {
            $query->whereBetween('date', [$start, $end]);
        }

        if ($this->request->filled('page_ids')) {
            $query->whereIn('page_id', (array) $this->request->input('page_ids'));
        }

        if ($this->request->filled('shop_ids')) {
            $query->whereIn('shop_id', (array) $this->request->input('shop_ids'));
        }

        // Matches RtsBaseQuery: scope through the shop's teams, so a team with no
        // connected shop matches nothing rather than leaving the data unfiltered.
        if ($this->request->filled('team_ids')) {
            $teamIds = (array) $this->request->input('team_ids');
            $query->whereHas('shop.teams', fn ($q) => $q->whereIn('teams.id', $teamIds));
        }

        return $query;
    }
}
