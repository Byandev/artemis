<?php

namespace Modules\Botcake\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Botcake\Models\Flow;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FlowController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        [$mode, $from, $to] = $this->resolveModeAndRange($request);

        $base = Flow::query()
            ->whereHas('page', fn ($query) => $query->where('workspace_id', $workspace->id))
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
            ])
            ->allowedSorts(['name', 'sent', 'total_phone_number', 'success_rate'])
            ->defaultSort('-sent')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/botcake/flows', [
            'workspace' => $workspace,
            'flows' => $flows,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page', 'mode', 'from', 'to']),
                'filter' => $request->input('filter', []),
            ],
        ]);
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
