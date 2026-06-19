<?php

namespace Modules\Botcake\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewBotcakeSequences->value, $workspace);

        [$mode, $from, $to] = $this->resolveModeAndRange($request);

        $base = Sequence::query()
            ->whereHas('page', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->when(
                TeamVisibility::shouldScope($request->user(), $workspace),
                fn ($q) => $q->whereHas('page', fn ($p) => $p->visibleTo($request->user(), $workspace)),
            )
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
                AllowedFilter::callback('page_ids', function ($query, $value) {
                    $ids = $this->parseIds($value);
                    if (! empty($ids)) {
                        $query->whereIn('botcake_sequences.page_id', $ids);
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
                            '(SELECT COALESCE(SUM(sent), 0) FROM botcake_sequence_daily_stats
                                WHERE botcake_sequence_daily_stats.sequence_id = botcake_sequences.id
                                  AND date BETWEEN ? AND ?) >= ?',
                            [$from, $to, $min]
                        );
                    } else {
                        $query->whereRaw(
                            '(SELECT COALESCE(SUM(sent), 0) FROM botcake_sequence_messages
                                WHERE botcake_sequence_messages.sequence_id = botcake_sequences.id) >= ?',
                            [$min]
                        );
                    }
                }),
            ])
            ->allowedSorts($allowedSorts)
            ->defaultSort('-total_sent')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/botcake/sequences', [
            'workspace' => $workspace->loadMissing([
                'shops' => function ($query) use ($request, $workspace) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name')
                        ->when(
                            TeamVisibility::shouldScope($request->user(), $workspace),
                            fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo($request->user(), $workspace)),
                        );
                },
                'pages' => function ($query) use ($request, $workspace) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name')
                        ->when(
                            TeamVisibility::shouldScope($request->user(), $workspace),
                            fn ($q) => $q->visibleTo($request->user(), $workspace),
                        );
                },
                'teams' => function ($query) use ($request, $workspace) {
                    $query->select('id', 'name', 'workspace_id')->orderBy('name')
                        ->when(
                            ! TeamVisibility::isUnrestricted($request->user(), $workspace),
                            fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $request->user()->id)),
                        );
                },
                'pageOwners:id,name',
            ]),
            'sequences' => $sequences,
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

        if (! $from || ! $to) {
            $from = now()->subDays(6)->toDateString();
            $to = now()->toDateString();
        }

        return ['historical', $from, $to];
    }
}
