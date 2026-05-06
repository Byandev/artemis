<?php

namespace Modules\Botcake\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Botcake\Http\Sorts\Sequence\SuccessRateSort;
use Modules\Botcake\Http\Sorts\Sequence\TotalPhoneNumberSort;
use Modules\Botcake\Http\Sorts\Sequence\TotalSentSort;
use Modules\Botcake\Models\Sequence;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class SequenceController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        [$mode, $from, $to] = $this->resolveModeAndRange($request);

        $base = Sequence::query()
            ->whereHas('page', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->with('page:id,name');

        if ($mode === 'historical') {
            $base->appendHistorical($from, $to);

            // In historical mode the aliased subselects can be sorted on
            // directly by their alias name — no custom Sort classes needed.
            $allowedSorts = ['name', 'total_sent', 'total_phone_number', 'success_rate'];
        } else {
            $base
                ->appendTotalSent()
                ->appendTotalPhoneNumber()
                ->appendSuccessRate();

            $allowedSorts = [
                'name',
                AllowedSort::custom('total_sent', new TotalSentSort),
                AllowedSort::custom('total_phone_number', new TotalPhoneNumberSort),
                AllowedSort::custom('success_rate', new SuccessRateSort),
            ];
        }

        $sequences = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts($allowedSorts)
            ->defaultSort('-total_sent')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/botcake/sequences', [
            'workspace' => $workspace,
            'sequences' => $sequences,
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

        if (! $from || ! $to) {
            $from = now()->subDays(6)->toDateString();
            $to = now()->toDateString();
        }

        return ['historical', $from, $to];
    }
}
