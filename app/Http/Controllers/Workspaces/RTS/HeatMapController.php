<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Queries\RtsHeatMapQuery;
use App\Support\PhilippineGeo;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Choropleth of RTS across the Philippines. Shares the RTS analytics filters and
 * the same province aggregation, but resolves every row to a GADM feature id so
 * the front end can shade the matching polygon in public/ph_geojson.json.
 */
class HeatMapController extends Controller
{
    use AuthorizesRequests;

    public function index(Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewRtsAnalytics->value, $workspace);

        $user = request()->user();

        return Inertia::render('workspaces/rts/heat-map', [
            'workspace' => $workspace->loadMissing([
                'shops' => fn ($q) => $q->select('id', 'name', 'workspace_id')->orderBy('name')
                    ->when(
                        TeamVisibility::shouldScope($user, $workspace),
                        fn ($s) => $s->whereHas('pages', fn ($p) => $p->visibleTo($user, $workspace)),
                    ),
                'pages' => fn ($q) => $q->select('id', 'name', 'workspace_id')->orderBy('name')
                    ->when(
                        TeamVisibility::shouldScope($user, $workspace),
                        fn ($p) => $p->visibleTo($user, $workspace),
                    ),
                'teams' => fn ($q) => $q->select('id', 'name', 'workspace_id')->orderBy('name')
                    ->when(
                        ! TeamVisibility::isUnrestricted($user, $workspace),
                        fn ($t) => $t->whereHas('members', fn ($m) => $m->where('users.id', $user->id)),
                    ),
                'pageOwners:id,name',
            ]),
        ]);
    }

    public function data(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize(Permission::ViewRtsAnalytics->value, $workspace);

        $groupBy = $request->input('group_by') === 'region' ? 'region' : 'province';

        $key = 'rts:'.$workspace->id.':heat-map:'.$groupBy.':'.md5((string) json_encode(
            $request->only(['start_date', 'end_date', 'page_ids', 'shop_ids', 'team_ids'])
        ));

        return response()->json(
            Cache::remember($key, 60, fn () => $this->build($request, $workspace, $groupBy))
        );
    }

    /**
     * @return array{group_by: string, rows: list<array<string, mixed>>, unmapped: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    private function build(Request $request, Workspace $workspace, string $groupBy): array
    {
        $query = new RtsHeatMapQuery($workspace, $request);

        $rows = $query->mapped($groupBy)
            ->map(fn ($record) => $this->mappedRow($record, $groupBy))
            ->values()
            ->all();

        $unmapped = $query->unmapped($groupBy)
            ->map(fn ($record) => $this->metrics($record))
            ->values()
            ->all();

        $totals = [
            'orders' => 0,
            'sales' => 0.0,
            'delivered_count' => 0,
            'delivered_amount' => 0.0,
            'returning_count' => 0,
            'returning_amount' => 0.0,
        ];

        foreach ([...$rows, ...$unmapped] as $row) {
            foreach ($totals as $field => $value) {
                $totals[$field] = $value + $row[$field];
            }
        }

        $outcomeValue = $totals['returning_amount'] + $totals['delivered_amount'];

        return [
            'group_by' => $groupBy,
            'rows' => $rows,
            'unmapped' => $unmapped,
            'available' => $query->availableRange(),
            'totals' => $totals + [
                'rts_rate_percentage' => $outcomeValue > 0
                    ? round($totals['returning_amount'] * 100 / $outcomeValue, 2)
                    : 0.0,
                'mapped_areas' => count($rows),
                'unmapped_areas' => count($unmapped),
            ],
        ];
    }

    /**
     * One shaded area. `gids` is a list because a region has no polygon of its own
     * — it shades every province polygon inside it — while a province is always
     * exactly one.
     *
     * @return array<string, mixed>
     */
    private function mappedRow(object $record, string $groupBy): array
    {
        if ($groupBy === 'region') {
            return [
                'gids' => PhilippineGeo::regionGids($record->region),
                'region' => $record->region,
                'province_id' => null,
            ] + $this->metrics($record);
        }

        $metrics = $this->metrics($record);

        // Name the polygon, not the first spelling behind it: GADM predates some
        // provincial splits and merges those provinces into one shape.
        $metrics['province_name'] = PhilippineGeo::provinceLabelForGid($record->gid)
            ?? $metrics['province_name'];

        return [
            'gids' => [$record->gid],
            'region' => null,
            'province_id' => $record->province_id ?? null,
        ] + $metrics;
    }

    /** @return array<string, mixed> */
    private function metrics(object $record): array
    {
        $province = $record->province_name ?? null;

        return [
            'province_name' => PhilippineGeo::provinceLabel($province) ?? $this->label($province),
            'orders' => (int) $record->orders,
            'sales' => (float) $record->sales,
            'delivered_count' => (int) $record->delivered_count,
            'delivered_amount' => (float) $record->delivered_amount,
            'returning_count' => (int) $record->returning_count,
            'returning_amount' => (float) $record->returning_amount,
            'rts_rate_percentage' => (float) $record->rts_rate_percentage,
        ];
    }

    /**
     * Fallback label for a province with no entry in Pancake's own list. Pancake
     * stores names hyphenated and inconsistently cased ("Davao-del-sur"), so the
     * raw value is not presentable on its own.
     */
    private function label(?string $name): ?string
    {
        $name = trim((string) $name);

        return $name === '' ? null : Str::title(str_replace('-', ' ', $name));
    }
}
