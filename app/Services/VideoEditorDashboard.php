<?php

namespace App\Services;

use App\Models\Workspace;
use App\Support\VideoEditor\DashboardFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;

/**
 * Computes the video-editor dashboard sections. Each section is resolved
 * independently so the per-section API can fetch only what it needs. Every
 * section respects the date / product / format filters; the leaderboard
 * intentionally ignores the editor scope so an editor can see their rank.
 */
class VideoEditorDashboard
{
    private const FINAL_APPROVED = 'approved';

    private const FINAL_FOR_REVISION = 'for_revision';

    private const REVIEW_REVISION = 'revision';

    private const REVIEW_AWAITING = ['for_approval', 'for_reapproval'];

    private const REVIEW_WAITING = 'waiting_for_submission';

    private const ADS_RUNNING = 'running';

    private const ADS_SCALE = 'scale';

    private const FORMAT_VIDEO = 'video';

    private const LIST_LIMIT = 12;

    private const LEADERBOARD_LIMIT = 10;

    /** Eager loads needed to derive a creative's status without N+1 queries. */
    private const STATUS_RELATIONS = [
        'product:id,title',
        'latestReview.reviewer:id,name',
    ];

    // ─── KPI cards (one endpoint each) ─────────────────────────────────────────

    /**
     * Total creatives, split by format for the card's sub-line.
     *
     * @return array{value: int, video: int, image: int}
     */
    public function totalCreatives(Workspace $workspace, DashboardFilters $filters): array
    {
        $creatives = $this->editorCreatives($workspace, $filters);
        $formats = $creatives->countBy('format');

        return [
            'value' => $creatives->count(),
            'video' => $formats->get(self::FORMAT_VIDEO, 0),
            'image' => $formats->get('image', 0),
        ];
    }

    /**
     * @return array{value: int}
     */
    public function awaitingReview(Workspace $workspace, DashboardFilters $filters): array
    {
        return ['value' => $this->editorCreatives($workspace, $filters)
            ->filter(fn (Creative $c) => $this->isAwaitingReview($c))
            ->count()];
    }

    /**
     * @return array{value: int}
     */
    public function needsRevision(Workspace $workspace, DashboardFilters $filters): array
    {
        return ['value' => $this->editorCreatives($workspace, $filters)
            ->filter(fn (Creative $c) => $this->isNeedsRevision($c))
            ->count()];
    }

    /**
     * Approved count plus the approval rate for the card's sub-line.
     *
     * @return array{value: int, approval_rate: int}
     */
    public function approved(Workspace $workspace, DashboardFilters $filters): array
    {
        $creatives = $this->editorCreatives($workspace, $filters);
        $total = $creatives->count();
        $approved = $creatives->where('final_status', self::FINAL_APPROVED)->count();

        return [
            'value' => $approved,
            'approval_rate' => $total > 0 ? (int) round($approved / $total * 100) : 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function ads(Workspace $workspace, DashboardFilters $filters): array
    {
        return $this->adsBreakdown($this->editorCreatives($workspace, $filters));
    }

    // ─── Pipeline ──────────────────────────────────────────────────────────────

    /**
     * @return array<string, int>
     */
    public function pipeline(Workspace $workspace, DashboardFilters $filters): array
    {
        $creatives = $this->editorCreatives($workspace, $filters);

        $status = $this->statusBreakdown($creatives);
        $ads = $this->adsBreakdown($creatives);

        return [
            'waiting' => $status['waiting'],
            'for_approval' => $status['for_approval'],
            'revision' => $status['revision'],
            'approved' => $status['approved'],
            'running_ads' => $ads[self::ADS_RUNNING] + $ads[self::ADS_SCALE],
        ];
    }

    // ─── Work lists ──────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function revisionList(Workspace $workspace, DashboardFilters $filters): array
    {
        return $this->editorCreatives($workspace, $filters)
            ->filter(fn (Creative $c) => $this->isNeedsRevision($c))
            ->sortByDesc(fn (Creative $c) => $c->latestReview?->created_at ?? $c->updated_at)
            ->take(self::LIST_LIMIT)
            ->map(fn (Creative $c) => $this->workItem($c))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function waitingList(Workspace $workspace, DashboardFilters $filters): array
    {
        return $this->editorCreatives($workspace, $filters)
            ->filter(fn (Creative $c) => $this->isWaiting($c))
            ->sortByDesc('creative_date')
            ->take(self::LIST_LIMIT)
            ->map(fn (Creative $c) => $this->workItem($c))
            ->values()
            ->all();
    }

    // ─── Charts ──────────────────────────────────────────────────────────────

    /**
     * @return array{categories: list<string>, video: list<int>, image: list<int>}
     */
    public function throughput(Workspace $workspace, DashboardFilters $filters): array
    {
        $creatives = $this->editorCreatives($workspace, $filters);

        // Bucket size + label format follow the chosen grouping.
        [$labelFmt, $floor, $advance] = match ($filters->group) {
            'daily' => ['M j', fn (CarbonImmutable $d) => $d->startOfDay(), fn (CarbonImmutable $d) => $d->addDay()],
            'monthly' => ['M Y', fn (CarbonImmutable $d) => $d->startOfMonth(), fn (CarbonImmutable $d) => $d->addMonth()],
            'yearly' => ['Y', fn (CarbonImmutable $d) => $d->startOfYear(), fn (CarbonImmutable $d) => $d->addYear()],
            default => ['M j', fn (CarbonImmutable $d) => $d->startOfWeek(), fn (CarbonImmutable $d) => $d->addWeek()],
        };

        // Pre-seed ordered, zero-filled buckets across the whole range.
        $buckets = [];
        for ($cursor = $floor($filters->dateFrom), $end = $floor($filters->dateTo); $cursor <= $end; $cursor = $advance($cursor)) {
            $buckets[$cursor->toDateString()] = ['label' => $cursor->format($labelFmt), 'video' => 0, 'image' => 0];
        }

        foreach ($creatives as $c) {
            if (! $c->creative_date) {
                continue;
            }
            $key = $floor(CarbonImmutable::parse($c->creative_date))->toDateString();
            if (isset($buckets[$key])) {
                $buckets[$key][$c->format === self::FORMAT_VIDEO ? 'video' : 'image']++;
            }
        }

        return [
            'categories' => array_column($buckets, 'label'),
            'video' => array_column($buckets, 'video'),
            'image' => array_column($buckets, 'image'),
        ];
    }

    // ─── Cross-editor sections ───────────────────────────────────────────────

    /**
     * Leaderboard across all editors (ignores the editor scope).
     *
     * @return list<array<string, mixed>>
     */
    public function leaderboard(Workspace $workspace, DashboardFilters $filters): array
    {
        return $this->baseQuery($workspace, $filters)
            ->whereNotNull('creator_id')
            ->with('creator:id,name')
            ->get()
            ->groupBy('creator_id')
            ->map(function (Collection $group) {
                $total = $group->count();
                $approved = $group->where('final_status', self::FINAL_APPROVED)->count();

                return [
                    'creator_id' => (int) $group->first()->creator_id,
                    'name' => $group->first()->creator?->name ?? 'Unknown',
                    'total' => $total,
                    'scaled' => $group->where('ads_status', self::ADS_SCALE)->count(),
                    'approval_rate' => $total > 0 ? round($approved / $total * 100) : 0,
                ];
            })
            ->sortByDesc('total')
            ->take(self::LEADERBOARD_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Per-day creation activity across all editors (ignores the editor scope):
     * who created creatives on each day and how many. Used by the dashboard's
     * calendar.
     *
     * @return array{from: string, to: string, days: list<array<string, mixed>>}
     */
    public function calendar(Workspace $workspace, DashboardFilters $filters): array
    {
        $days = $this->baseQuery($workspace, $filters)
            ->whereNotNull('creator_id')
            ->whereNotNull('creative_date')
            ->with('creator:id,name')
            ->get(['id', 'creator_id', 'creative_date'])
            ->groupBy(fn (Creative $c) => CarbonImmutable::parse($c->creative_date)->toDateString())
            ->map(function (Collection $group, string $date) {
                $creators = $group
                    ->groupBy('creator_id')
                    ->map(fn (Collection $byCreator) => [
                        'creator_id' => (int) $byCreator->first()->creator_id,
                        'name' => $byCreator->first()->creator?->name ?? 'Unknown',
                        'count' => $byCreator->count(),
                    ])
                    ->sortByDesc('count')
                    ->values()
                    ->all();

                return [
                    'date' => $date,
                    'total' => $group->count(),
                    'creators' => $creators,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        return [
            'from' => $filters->dateFrom->toDateString(),
            'to' => $filters->dateTo->toDateString(),
            'days' => $days,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivity(Workspace $workspace, DashboardFilters $filters): array
    {
        ['from' => $from, 'to' => $to] = $filters->dateBounds();

        return CreativeReview::query()
            ->whereHas('creative', fn (Builder $q) => $q
                ->where('workspace_id', $workspace->id)
                ->whereBetween('creative_date', [$from, $to])
                ->when($filters->userIds, fn (Builder $q) => $q->whereIn('creator_id', $filters->userIds)))
            ->with(['reviewer:id,name', 'creative:id,name'])
            ->latest()
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (CreativeReview $r) => [
                'id' => $r->id,
                'creative_id' => $r->creative_id,
                'creative_name' => $r->creative?->name,
                'status' => $r->status,
                'feedback' => $r->feedback,
                'reviewer' => $r->reviewer?->name,
                'created_at' => $r->created_at?->format('M j, Y g:i A'),
            ])
            ->values()
            ->all();
    }

    // ─── Query helpers ───────────────────────────────────────────────────────

    private function baseQuery(Workspace $workspace, DashboardFilters $filters): Builder
    {
        ['from' => $from, 'to' => $to] = $filters->dateBounds();

        return Creative::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('creative_date', [$from, $to])
            ->when($filters->productIds, fn (Builder $q) => $q->whereIn('product_id', $filters->productIds))
            ->when($filters->formats, fn (Builder $q) => $q->whereIn('format', $filters->formats));
    }

    /**
     * Creatives owned by the selected editor(s), eager-loaded for status
     * derivation. Editor scope defaults to the signed-in user.
     *
     * @return Collection<int, Creative>
     */
    private function editorCreatives(Workspace $workspace, DashboardFilters $filters): Collection
    {
        return $this->baseQuery($workspace, $filters)
            ->when($filters->userIds, fn (Builder $q) => $q->whereIn('creator_id', $filters->userIds))
            ->with(self::STATUS_RELATIONS)
            ->get();
    }

    // ─── Aggregation helpers ─────────────────────────────────────────────────

    /**
     * Single-pass status tally. The states are not mutually exclusive (a
     * revision flag can co-exist with approval), so each is counted on its own.
     *
     * @param  Collection<int, Creative>  $creatives
     * @return array{waiting: int, for_approval: int, revision: int, approved: int}
     */
    private function statusBreakdown(Collection $creatives): array
    {
        $counts = ['waiting' => 0, 'for_approval' => 0, 'revision' => 0, 'approved' => 0];

        foreach ($creatives as $c) {
            $counts['approved'] += $c->final_status === self::FINAL_APPROVED ? 1 : 0;
            $counts['revision'] += $this->isNeedsRevision($c) ? 1 : 0;
            $counts['for_approval'] += $this->isAwaitingReview($c) ? 1 : 0;
            $counts['waiting'] += $this->isWaiting($c) ? 1 : 0;
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Creative>  $creatives
     * @return array<string, int>
     */
    private function adsBreakdown(Collection $creatives): array
    {
        $counts = $creatives->countBy('ads_status');

        return [
            'pending' => $counts->get('pending', 0),
            self::ADS_RUNNING => $counts->get(self::ADS_RUNNING, 0),
            self::ADS_SCALE => $counts->get(self::ADS_SCALE, 0),
            'kill' => $counts->get('kill', 0),
        ];
    }

    // ─── Status derivation ───────────────────────────────────────────────────

    private function isNeedsRevision(Creative $c): bool
    {
        return $c->final_status === self::FINAL_FOR_REVISION
            || $c->latestReview?->status === self::REVIEW_REVISION;
    }

    private function isAwaitingReview(Creative $c): bool
    {
        return $c->final_status !== self::FINAL_APPROVED
            && in_array($c->latestReview?->status, self::REVIEW_AWAITING, true);
    }

    private function isWaiting(Creative $c): bool
    {
        if ($c->final_status === self::FINAL_APPROVED) {
            return false;
        }

        $status = $c->latestReview?->status;

        return $status === null || $status === self::REVIEW_WAITING;
    }

    /**
     * @return array<string, mixed>
     */
    private function workItem(Creative $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'format' => $c->format,
            'creative_date' => $c->creative_date?->format('M j, Y'),
            'product' => $c->product?->title,
            'final_status' => $c->final_status,
            'feedback' => $c->latestReview?->feedback,
            'reviewer' => $c->latestReview?->reviewer?->name,
        ];
    }
}
