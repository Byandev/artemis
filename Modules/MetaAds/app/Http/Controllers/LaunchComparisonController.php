<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;

class LaunchComparisonController extends Controller
{
    /** Rows per page; the first is the default. */
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    private const DAY_PRESETS = [3, 7, 14, 30];

    private const DEFAULT_DAYS = 7;

    private const MAX_DAYS = 90;

    /**
     * Launch Comparison — lines launches that started on different dates up by
     * day number (Day 1 = the item's start date) instead of calendar date, so a
     * campaign started Sept 1 and one started Sept 2 read side by side.
     *
     * Metrics come back as the same raw insight columns the Ads Manager grid
     * aggregates; the page derives computed metrics (ROAS, CPA, CTR…) with the
     * grid's own formulas.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $level = $this->resolveLevel($request);
        $days = $this->resolveDays($request);
        $config = $this->levelConfig($level);

        $accounts = AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->visibleTo($request->user(), $workspace)
            ->select('meta_ads_accounts.id', 'meta_ads_accounts.name')
            ->orderBy('meta_ads_accounts.name')
            ->get()
            ->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name]);

        $allAccountIds = $accounts->pluck('id');
        $accountIds = $this->resolveSelectedAccounts($request, $allAccountIds);

        $perPage = $this->resolvePerPage($request);
        [$startFrom, $startTo] = $this->resolveStartRange($request);

        // Every launched item — or those that started within the picked
        // range — newest first, a page at a time.
        $page = $accountIds->isEmpty()
            ? null
            : $this->launchedQuery($config, $accountIds->all())
                ->when($startFrom, fn ($q) => $q->where("{$config['table']}.start_time", '>=', $startFrom->copy()->startOfDay()))
                ->when($startTo, fn ($q) => $q->where("{$config['table']}.start_time", '<', $startTo->copy()->addDay()->startOfDay()))
                ->paginate($perPage)
                ->withQueryString();

        return Inertia::render('workspaces/integrations/meta-ads/launch-comparison', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'accounts' => $accounts,
            'selectedAccounts' => $accountIds->values(),
            'level' => $level,
            'days' => $days,
            'dayPresets' => self::DAY_PRESETS,
            'maxDays' => self::MAX_DAYS,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'startRange' => [
                'from' => $startFrom?->toDateString(),
                'to' => $startTo?->toDateString(),
            ],
            'items' => $page ? $this->alignedSeries($config, collect($page->items()), $days) : [],
            'pagination' => [
                'currentPage' => $page?->currentPage() ?? 1,
                'lastPage' => $page?->lastPage() ?? 1,
                'perPage' => $perPage,
                'total' => $page?->total() ?? 0,
                'from' => $page?->firstItem(),
                'to' => $page?->lastItem(),
            ],
        ]);
    }

    /**
     * Per-level query shape: the entity table, the insights column that keys
     * it, and the parent whose name disambiguates look-alike item names.
     *
     * @return array{table: string, key: string, parent_table: ?string, parent_key: ?string}
     */
    private function levelConfig(string $level): array
    {
        return match ($level) {
            'ad_set' => [
                'table' => 'meta_ads_sets',
                'key' => 'meta_ads_set_id',
                'parent_table' => 'meta_ads_campaigns',
                'parent_key' => 'meta_ads_campaign_id',
            ],
            'ad' => [
                'table' => 'meta_ads_ads',
                'key' => 'meta_ads_ad_id',
                'parent_table' => 'meta_ads_sets',
                'parent_key' => 'meta_ads_set_id',
            ],
            default => [
                'table' => 'meta_ads_campaigns',
                'key' => 'meta_ads_campaign_id',
                'parent_table' => null,
                'parent_key' => null,
            ],
        };
    }

    /**
     * Items at this level that have launched — start time known and not in the
     * future — in the visible accounts, newest first, with the parent's name to
     * tell look-alike names apart.
     */
    private function launchedQuery(array $config, array $accountIds): Builder
    {
        $table = $config['table'];

        $selects = ["{$table}.id", "{$table}.name", "{$table}.start_time"];
        if ($config['parent_table']) {
            $selects[] = 'parent.name as parent_name';
        }

        return DB::table($table)
            ->when($config['parent_table'], fn ($q) => $q->leftJoin(
                "{$config['parent_table']} as parent",
                'parent.id',
                '=',
                "{$table}.{$config['parent_key']}",
            ))
            ->whereIn("{$table}.meta_ads_account_id", $accountIds)
            ->whereNotNull("{$table}.start_time")
            ->where("{$table}.start_time", '<=', Carbon::now())
            ->orderByDesc("{$table}.start_time")
            ->orderByDesc("{$table}.id")
            ->select($selects);
    }

    /**
     * Optional start-date window from `start_from` / `start_to` (Y-m-d, both
     * inclusive). Either end may be open; an unparseable date is ignored, and
     * a reversed pair is swapped rather than matching nothing.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveStartRange(Request $request): array
    {
        $parse = function (mixed $value): ?Carbon {
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return null;
            }

            try {
                return Carbon::createFromFormat('!Y-m-d', $value);
            } catch (\Throwable) {
                return null;
            }
        };

        $from = $parse($request->query('start_from'));
        $to = $parse($request->query('start_to'));

        return $from && $to && $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', self::PER_PAGE_OPTIONS[0]);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];
    }

    /**
     * Each item's insights over its own first $days days, one slot per day
     * number. Day 1 is the item's start date; a past day with no delivery is
     * zeroed, and a day that hasn't happened yet is null so the page shows it
     * blank rather than as a real 0. Today with no insights yet is null too:
     * it hasn't synced, not "spent nothing" — as a 0 every line would plunge
     * on its newest day.
     *
     * @param  Collection<int, object>  $entities  rows from launchedQuery(), in display order
     * @return array<int, array{id: string, name: string, parent: ?string, start_date: string, points: array<int, array<string, float|string>|null>}>
     */
    private function alignedSeries(array $config, Collection $entities, int $days): array
    {
        $table = $config['table'];
        $key = $config['key'];

        $selects = [
            DB::raw("meta_ads_insights.{$key} AS item_id"),
            DB::raw('meta_ads_insights.date AS date'),
        ];
        foreach (AdsManagerController::INSIGHTS_METRICS as $col) {
            $selects[] = DB::raw("COALESCE(SUM(meta_ads_insights.{$col}), 0) AS {$col}");
        }

        // Each item's own window, [start date, start date + $days), in one
        // query: the join gives every insight row its item's start time.
        $rows = DB::table('meta_ads_insights')
            ->join("{$table} as e", 'e.id', '=', "meta_ads_insights.{$key}")
            ->whereIn('e.id', $entities->pluck('id'))
            ->whereRaw('meta_ads_insights.date >= DATE(e.start_time)')
            ->whereRaw('meta_ads_insights.date < DATE(e.start_time) + INTERVAL ? DAY', [$days])
            ->groupBy("meta_ads_insights.{$key}", 'meta_ads_insights.date')
            ->get($selects)
            ->groupBy(fn ($row) => (string) $row->item_id)
            ->map(fn ($group) => $group->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString()));

        $today = Carbon::today();
        $zero = array_fill_keys(AdsManagerController::INSIGHTS_METRICS, 0.0);

        return $entities->map(function ($entity) use ($rows, $days, $today, $zero) {
            $id = (string) $entity->id;
            $start = Carbon::parse($entity->start_time)->startOfDay();
            $byDate = $rows->get($id, collect());
            $points = [];

            for ($n = 0; $n < $days; $n++) {
                $date = $start->copy()->addDays($n);

                $row = $byDate->get($date->toDateString());

                if ($date->gt($today) || (! $row && $date->eq($today))) {
                    $points[] = null;

                    continue;
                }

                $points[] = [
                    'date' => $date->toDateString(),
                    ...($row ? array_map(
                        fn ($col) => (float) $row->{$col},
                        array_combine(AdsManagerController::INSIGHTS_METRICS, AdsManagerController::INSIGHTS_METRICS),
                    ) : $zero),
                ];
            }

            return [
                'id' => $id,
                'name' => (string) $entity->name,
                'parent' => $entity->parent_name ?? null,
                'start_date' => $start->toDateString(),
                'points' => $points,
            ];
        })->values()->all();
    }

    private function resolveLevel(Request $request): string
    {
        $level = (string) $request->query('level', 'campaign');

        return in_array($level, ['campaign', 'ad_set', 'ad'], true) ? $level : 'campaign';
    }

    /** Any whole number of days from 1 to MAX_DAYS; the presets are just shortcuts. */
    private function resolveDays(Request $request): int
    {
        $days = filter_var($request->query('days'), FILTER_VALIDATE_INT);

        return $days === false ? self::DEFAULT_DAYS : max(1, min(self::MAX_DAYS, $days));
    }

    /** Mirrors the Ads Manager: no `accounts[]` means every visible account. */
    private function resolveSelectedAccounts(Request $request, Collection $allAccountIds): Collection
    {
        $requested = $request->query('accounts');

        if (! is_array($requested) || count($requested) === 0) {
            return $allAccountIds->values();
        }

        return $allAccountIds->intersect(array_map('strval', $requested))->values();
    }
}
