<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\EntityStatusOverride;
use Modules\MetaAds\Models\MonitorEvaluation;
use Modules\MetaAds\Models\MonitorStatusRule;
use Modules\MetaAds\Services\EntityMonitorService;

class EntityMonitorController extends Controller
{
    private const LEVELS = ['campaign', 'ad_set', 'ad'];

    /** Metrics that can appear in a status condition (computed ratios + raw columns). */
    private const METRICS = [
        'roas', 'cpa', 'cpc', 'ctr', 'cpm', 'cost_per_lead', 'cost_per_messaging_conversation',
        'spend', 'impressions', 'clicks', 'purchases', 'purchase_value', 'leads', 'conversions',
        'messaging_conversations_started',
    ];

    private const OPERATORS = ['>', '<', '>=', '<=', '='];

    private const WINDOWS = ['first_3_days', 'first_7_days', 'lifetime'];

    /** Entity table per level, for the visibility check. */
    private const LEVEL_TABLES = [
        'campaign' => 'meta_ads_campaigns',
        'ad_set' => 'meta_ads_sets',
        'ad' => 'meta_ads_ads',
    ];

    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        MonitorStatusRule::ensureDefaultsFor($workspace->id);

        [$from, $to] = $this->resolveStartedRange($request);

        return Inertia::render('workspaces/integrations/meta-ads/entity-monitor', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'accounts' => $this->visibleAccounts($request, $workspace)
                ->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name])
                ->values(),
            'rules' => $this->rulesPayload($workspace),
            'options' => [
                'levels' => self::LEVELS,
                'metrics' => self::METRICS,
                'operators' => self::OPERATORS,
                'windows' => self::WINDOWS,
                'conditionOperators' => ['and', 'or'],
                'statuses' => MonitorStatusRule::STATUSES,
            ],
            'query' => [
                'level' => $this->resolveLevel($request),
                'startedFrom' => $from,
                'startedTo' => $to,
            ],
        ]);
    }

    public function data(Request $request, Workspace $workspace, EntityMonitorService $service): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        MonitorStatusRule::ensureDefaultsFor($workspace->id);

        $level = $this->resolveLevel($request);
        [$from, $to] = $this->resolveStartedRange($request);

        $accountIds = $this->visibleAccounts($request, $workspace)
            ->map(fn ($a) => (string) $a->id)
            ->all();

        $rows = $service->buildRows($workspace, $level, $accountIds, $from, $to, $this->rules($workspace));

        $this->attachOverridesAndTrail($workspace, $level, $rows);

        return response()->json(['rows' => $rows]);
    }

    public function setStatus(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $data = $request->validate([
            'level' => ['required', Rule::in(self::LEVELS)],
            'entity_id' => ['required', 'string'],
            'final_status' => ['nullable', Rule::in(MonitorStatusRule::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ensureEntityVisible($request, $workspace, $data['level'], $data['entity_id']);

        $base = [
            'workspace_id' => $workspace->id,
            'level' => $data['level'],
            'entity_id' => $data['entity_id'],
        ];

        // Clearing both the status and the note removes the override entirely,
        // reverting the row to the suggested status.
        if (empty($data['final_status']) && empty($data['notes'])) {
            EntityStatusOverride::where($base)->delete();

            return back()->with('success', 'Override cleared.');
        }

        EntityStatusOverride::updateOrCreate($base, [
            'final_status' => $data['final_status'] ?? null,
            'notes' => $data['notes'] ?? null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Status updated.');
    }

    public function updateRules(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.status' => ['required', Rule::in(MonitorStatusRule::STATUSES)],
            'rules.*.condition_operator' => ['required', Rule::in(['and', 'or'])],
            'rules.*.is_active' => ['boolean'],
            'rules.*.conditions' => ['required', 'array', 'min:1'],
            'rules.*.conditions.*.metric' => ['required', Rule::in(self::METRICS)],
            'rules.*.conditions.*.operator' => ['required', Rule::in(self::OPERATORS)],
            'rules.*.conditions.*.value' => ['required', 'numeric'],
            'rules.*.conditions.*.window' => ['required', Rule::in(self::WINDOWS)],
        ]);

        foreach ($data['rules'] as $r) {
            $rule = MonitorStatusRule::updateOrCreate(
                ['workspace_id' => $workspace->id, 'status' => $r['status']],
                ['condition_operator' => $r['condition_operator'], 'is_active' => $r['is_active'] ?? true],
            );

            $rule->conditions()->delete();

            foreach ($r['conditions'] as $c) {
                $rule->conditions()->create([
                    'metric' => $c['metric'],
                    'operator' => $c['operator'],
                    'value' => $c['value'],
                    'window' => $c['window'],
                ]);
            }
        }

        return back()->with('success', 'Monitor rules updated.');
    }

    /**
     * Attach the human override and the daily status trail to each row, and
     * resolve the effective final status (override wins over the suggestion).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachOverridesAndTrail(Workspace $workspace, string $level, array &$rows): void
    {
        if (empty($rows)) {
            return;
        }

        $ids = array_column($rows, 'entity_id');

        $overrides = EntityStatusOverride::where('workspace_id', $workspace->id)
            ->where('level', $level)
            ->whereIn('entity_id', $ids)
            ->get()
            ->keyBy(fn ($o) => (string) $o->entity_id);

        $trail = MonitorEvaluation::where('workspace_id', $workspace->id)
            ->where('level', $level)
            ->whereIn('entity_id', $ids)
            ->orderBy('date')
            ->get(['entity_id', 'date', 'suggested_status'])
            ->groupBy(fn ($e) => (string) $e->entity_id);

        foreach ($rows as &$row) {
            $id = (string) $row['entity_id'];
            $override = $overrides->get($id);

            $row['override'] = $override ? [
                'final_status' => $override->final_status,
                'notes' => $override->notes,
                'decided_at' => optional($override->decided_at)->toIso8601String(),
            ] : null;

            $row['final_status'] = $override && $override->final_status
                ? $override->final_status
                : $row['suggested_status'];

            $row['trail'] = $trail->get($id, collect())
                ->map(fn ($e) => ['date' => $e->date->toDateString(), 'status' => $e->suggested_status])
                ->values()
                ->all();
        }
    }

    private function ensureEntityVisible(Request $request, Workspace $workspace, string $level, string $entityId): void
    {
        $accountId = DB::table(self::LEVEL_TABLES[$level])->where('id', $entityId)->value('meta_ads_account_id');

        $visible = $accountId && AdAccount::forWorkspace($workspace)
            ->visibleTo($request->user(), $workspace)
            ->where('meta_ads_accounts.id', $accountId)
            ->exists();

        abort_unless($visible, 403, 'You do not have access to this entity.');
    }

    /**
     * @return Collection<int, MonitorStatusRule>
     */
    private function rules(Workspace $workspace): Collection
    {
        return MonitorStatusRule::where('workspace_id', $workspace->id)->with('conditions')->get();
    }

    /**
     * Rules shaped for the config form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rulesPayload(Workspace $workspace): array
    {
        return $this->rules($workspace)
            ->sortBy(fn ($r) => array_search($r->status, MonitorStatusRule::STATUSES, true))
            ->map(fn (MonitorStatusRule $r) => [
                'status' => $r->status,
                'condition_operator' => $r->condition_operator,
                'is_active' => $r->is_active,
                'conditions' => $r->conditions->map(fn ($c) => [
                    'metric' => $c->metric,
                    'operator' => $c->operator,
                    'value' => (float) $c->value,
                    'window' => $c->window,
                ])->values(),
            ])
            ->values()
            ->all();
    }

    private function visibleAccounts(Request $request, Workspace $workspace): Collection
    {
        return AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->visibleTo($request->user(), $workspace)
            ->orderBy('meta_ads_accounts.name')
            ->get(['meta_ads_accounts.id', 'meta_ads_accounts.name']);
    }

    private function resolveLevel(Request $request): string
    {
        $level = (string) $request->query('level', 'campaign');

        return in_array($level, self::LEVELS, true) ? $level : 'campaign';
    }

    /**
     * Range the entity's first spend day must fall within. Defaults to the last
     * 30 days so the board shows current/recent tests.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveStartedRange(Request $request): array
    {
        $from = $request->query('startedFrom');
        $to = $request->query('startedTo');

        if ($from && $to) {
            return [$from, $to];
        }

        $today = Carbon::today();

        return [$today->copy()->subDays(29)->toDateString(), $today->toDateString()];
    }
}
