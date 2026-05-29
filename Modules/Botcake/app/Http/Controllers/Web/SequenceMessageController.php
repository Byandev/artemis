<?php

namespace Modules\Botcake\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Botcake\Http\Sorts\SequenceMessage\SuccessRateSort;
use Modules\Botcake\Models\SequenceMessage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class SequenceMessageController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(
            Permission::ViewBotcakeSequenceMessages->value,
            $workspace,
        );

        [$mode, $from, $to] = $this->resolveModeAndRange($request);

        $base = SequenceMessage::query()
            ->whereHas('sequence.page', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->with(['sequence:id,name,page_id', 'sequence.page:id,name']);

        if ($mode === 'historical') {
            $base->appendHistorical($from, $to);
            $allowedSorts = ['name', 'sent', 'total_phone_number', 'success_rate'];
        } else {
            $base->select('botcake_sequence_messages.*')->appendSuccessRate();
            $allowedSorts = [
                'name',
                'sent',
                'total_phone_number',
                AllowedSort::custom('success_rate', new SuccessRateSort),
            ];
        }

        $messages = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::callback('sequence_ids', function ($query, $value) {
                    $ids = $this->parseIds($value);
                    if (! empty($ids)) {
                        $query->whereIn('botcake_sequence_messages.sequence_id', $ids);
                    }
                }),
                AllowedFilter::callback('page_ids', function ($query, $value) {
                    $ids = $this->parseIds($value);
                    if (! empty($ids)) {
                        $query->whereHas('sequence', fn ($q) => $q->whereIn('page_id', $ids));
                    }
                }),
                AllowedFilter::callback('shop_ids', function ($query, $value) {
                    $ids = $this->parseIds($value);
                    if (! empty($ids)) {
                        $query->whereHas('sequence.page', fn ($q) => $q->whereIn('shop_id', $ids));
                    }
                }),
                AllowedFilter::callback('team_ids', function ($query, $value) {
                    $ids = $this->parseIds($value);
                    if (! empty($ids)) {
                        $query->whereHas('sequence.page.owner.teams', fn ($q) => $q->whereIn('teams.id', $ids));
                    }
                }),
                AllowedFilter::callback('sent_min', function ($query, $value) use ($mode, $from, $to) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $min = (int) $value;
                    if ($mode === 'historical') {
                        $query->whereRaw(
                            '(SELECT COALESCE(SUM(sent), 0) FROM botcake_sequence_message_daily_stats
                                WHERE botcake_sequence_message_daily_stats.sequence_message_id = botcake_sequence_messages.id
                                  AND date BETWEEN ? AND ?) >= ?',
                            [$from, $to, $min]
                        );
                    } else {
                        $query->where('botcake_sequence_messages.sent', '>=', $min);
                    }
                }),
            ])
            ->allowedSorts($allowedSorts)
            ->defaultSort('-sent')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/botcake/sequence-messages', [
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
            'messages' => $messages,
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
