<?php

namespace Modules\Botcake\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Botcake\Models\Flow;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FlowController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewBotcakeFlows->value, $workspace);

        [$mode, $from, $to] = $this->resolveModeAndRange($request);

        $base = Flow::query()
            ->whereHas('page', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->when(
                TeamVisibility::shouldScope($request->user(), $workspace),
                fn ($q) => $q->whereHas('page', fn ($p) => $p->visibleTo($request->user(), $workspace)),
            )
            ->with('page:id,name');

        if ($mode === 'historical') {
            // Replace cumulative columns with date-bounded sums from the daily
            // stat table. Keep only non-stat Flow columns in the SELECT so the
            // historical aliases don't collide with the table columns.
            $base
                ->select([
                    'botcake_flows.id',
                    'botcake_flows.page_id',
                    'botcake_flows.parent_id',
                    'botcake_flows.is_removed',
                    'botcake_flows.name',
                    'botcake_flows.created_at',
                    'botcake_flows.updated_at',
                ])
                ->appendHistorical($from, $to);
        } else {
            $base
                ->select('botcake_flows.*')
                ->appendSuccessRate();
        }

        $flows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::callback('page_ids', function ($query, $value) {
                    $ids = $this->parseIds($value);
                    if (! empty($ids)) {
                        $query->whereIn('botcake_flows.page_id', $ids);
                    }
                }),
                AllowedFilter::callback('shop_ids', function ($query, $value) {
                    $ids = $this->parseIds($value);
                    if (! empty($ids)) {
                        $query->whereHas('page', fn ($q) => $q->whereIn('shop_id', $ids));
                    }
                }),
                AllowedFilter::callback('team_ids', function ($query, $value) {
                    $ids = $this->parseIds($value);
                    if (! empty($ids)) {
                        $query->whereHas('page.owner.teams', fn ($q) => $q->whereIn('teams.id', $ids));
                    }
                }),
                AllowedFilter::callback('sent_min', function ($query, $value) use ($mode, $from, $to) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $min = (int) $value;
                    if ($mode === 'historical') {
                        $query->whereRaw(
                            '(SELECT COALESCE(SUM(sent), 0) FROM botcake_flow_daily_stats
                                WHERE botcake_flow_daily_stats.flow_id = botcake_flows.id
                                  AND date BETWEEN ? AND ?) >= ?',
                            [$from, $to, $min]
                        );
                    } else {
                        $query->where('botcake_flows.sent', '>=', $min);
                    }
                }),
            ])
            ->allowedSorts(['name', 'sent', 'total_phone_number', 'success_rate'])
            ->defaultSort('-sent')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/botcake/flows', [
            'workspace' => $workspace->loadMissing([
                'shops' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
                'pages' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
                'teams' => function ($query) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name');
                },
                'pageOwners:id,name',
            ]),
            'flows' => $flows,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page', 'mode', 'from', 'to']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    private function parseIds($value): array
    {
        $arr = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(
            array_map('intval', $arr),
            fn ($n) => $n > 0,
        ));
    }

    /**
     * @return array{0: 'overall'|'historical', 1: string|null, 2: string|null}
     */
    private function resolveModeAndRange(Request $request): array
    {
        $mode = $request->input('mode') === 'historical' ? 'historical' : 'overall';

        if ($mode !== 'historical') {
            return ['overall', null, null];
        }

        $from = $request->input('from');
        $to = $request->input('to');

        // Default historical window: last 7 days, anchored to today.
        if (! $from || ! $to) {
            $from = now()->subDays(6)->toDateString();
            $to = now()->toDateString();
        }

        return ['historical', $from, $to];
    }
}
