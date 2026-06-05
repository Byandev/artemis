<?php

namespace Modules\Creatives\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    /**
     * Editor-focused dashboard. Defaults to the signed-in editor's own
     * creatives; managers can switch the editor filter to "all" or another
     * person. All sections respect the date / product / format filters; the
     * leaderboard intentionally ignores the editor filter so an editor can see
     * where they rank against the rest of the team.
     */
    public function __invoke(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        $this->authorize(Permission::ViewCreatives->value, $workspace);

        $userId = $request->user()->id;

        // ─── Resolve filters ──────────────────────────────────────────────
        // Date range mirrors the main dashboard: month-to-date by default.
        $dateFrom = $request->filled('date_from')
            ? CarbonImmutable::parse($request->input('date_from'))
            : CarbonImmutable::today()->startOfMonth();
        $dateTo = $request->filled('date_to')
            ? CarbonImmutable::parse($request->input('date_to'))
            : CarbonImmutable::today();

        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;
        $format = in_array($request->input('format'), ['video', 'image'], true)
            ? $request->input('format')
            : null;

        // Always scoped to the signed-in editor — this is their personal board.
        $creatives = $this->baseQuery($workspace, $dateFrom, $dateTo, $productId, $format)
            ->where('creator_id', $userId)
            ->with([
                'creator:id,name',
                'product:id,title',
                'latestReview.reviewer:id,name',
            ])
            ->get();

        // ─── KPI cards ─────────────────────────────────────────────────────
        $total = $creatives->count();
        $videoCount = $creatives->where('format', 'video')->count();
        $imageCount = $creatives->where('format', 'image')->count();

        $needsRevision = $creatives->filter(fn ($c) => $this->isNeedsRevision($c));
        $awaitingReview = $creatives->filter(fn ($c) => $this->isAwaitingReview($c));
        $waiting = $creatives->filter(fn ($c) => $this->isWaiting($c));
        $approvedCount = $creatives->where('final_status', 'approved')->count();

        $kpis = [
            'total' => $total,
            'video' => $videoCount,
            'image' => $imageCount,
            'awaiting_review' => $awaitingReview->count(),
            'needs_revision' => $needsRevision->count(),
            'approved' => $approvedCount,
            'approval_rate' => $total > 0 ? round($approvedCount / $total * 100) : 0,
            'ads' => [
                'pending' => $creatives->where('ads_status', 'pending')->count(),
                'running' => $creatives->where('ads_status', 'running')->count(),
                'scale' => $creatives->where('ads_status', 'scale')->count(),
                'kill' => $creatives->where('ads_status', 'kill')->count(),
            ],
        ];

        // ─── Pipeline funnel ───────────────────────────────────────────────
        $pipeline = [
            'waiting' => $waiting->count(),
            'for_approval' => $awaitingReview->count(),
            'revision' => $needsRevision->count(),
            'approved' => $approvedCount,
            'running_ads' => $creatives->whereIn('ads_status', ['running', 'scale'])->count(),
        ];

        // ─── My Work action panels ─────────────────────────────────────────
        $revisionList = $needsRevision
            ->sortByDesc(fn ($c) => optional($c->latestReview)->created_at ?? $c->updated_at)
            ->take(12)
            ->map(fn ($c) => $this->workItem($c))
            ->values()
            ->all();

        $waitingList = $waiting
            ->sortByDesc('creative_date')
            ->take(12)
            ->map(fn ($c) => $this->workItem($c))
            ->values()
            ->all();

        // ─── Throughput (weekly buckets, video vs image) ───────────────────
        $throughput = $this->throughput($creatives, $dateFrom, $dateTo);

        // ─── Leaderboard (all editors, ignores the editor filter) ──────────
        $leaderboard = $this->leaderboard($workspace, $dateFrom, $dateTo, $productId, $format);

        // ─── Recent activity (reviews on the in-scope creatives) ───────────
        $recentActivity = $this->recentActivity($workspace, $dateFrom, $dateTo, $userId);

        return Inertia::render('workspaces/creatives/dashboard', [
            'workspace' => $workspace,
            'currentUserId' => $userId,
            'kpis' => $kpis,
            'pipeline' => $pipeline,
            'revisionList' => $revisionList,
            'waitingList' => $waitingList,
            'throughput' => $throughput,
            'leaderboard' => $leaderboard,
            'recentActivity' => $recentActivity,
            'products' => Product::where('workspace_id', $workspace->id)
                ->select('id', 'title')->orderBy('title')->get(),
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'product_id' => $productId ? (string) $productId : 'all',
                'format' => $format ?? 'all',
            ],
        ]);
    }

    // ─── Query helpers ─────────────────────────────────────────────────────

    private function baseQuery(
        Workspace $workspace,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        ?int $productId,
        ?string $format
    ): Builder {
        return Creative::where('workspace_id', $workspace->id)
            ->whereBetween('creative_date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
            ->when($productId, fn (Builder $q) => $q->where('product_id', $productId))
            ->when($format, fn (Builder $q) => $q->where('format', $format));
    }

    /**
     * @return array{categories: list<string>, video: list<int>, image: list<int>}
     */
    private function throughput($creatives, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): array
    {
        // Build ordered weekly buckets across the range, keyed by week start.
        $buckets = [];
        $cursor = $dateFrom->startOfWeek();
        $end = $dateTo->startOfWeek();
        while ($cursor <= $end) {
            $buckets[$cursor->format('Y-m-d')] = ['label' => $cursor->format('M j'), 'video' => 0, 'image' => 0];
            $cursor = $cursor->addWeek();
        }

        foreach ($creatives as $c) {
            if (! $c->creative_date) {
                continue;
            }
            $key = CarbonImmutable::parse($c->creative_date)->startOfWeek()->format('Y-m-d');
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key][$c->format === 'video' ? 'video' : 'image']++;
        }

        return [
            'categories' => array_values(array_map(fn ($b) => $b['label'], $buckets)),
            'video' => array_values(array_map(fn ($b) => $b['video'], $buckets)),
            'image' => array_values(array_map(fn ($b) => $b['image'], $buckets)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leaderboard(
        Workspace $workspace,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        ?int $productId,
        ?string $format
    ): array {
        $rows = $this->baseQuery($workspace, $dateFrom, $dateTo, $productId, $format)
            ->whereNotNull('creator_id')
            ->with('creator:id,name')
            ->get()
            ->groupBy('creator_id')
            ->map(function ($group) {
                $total = $group->count();
                $approved = $group->where('final_status', 'approved')->count();

                return [
                    'creator_id' => (int) $group->first()->creator_id,
                    'name' => optional($group->first()->creator)->name ?? 'Unknown',
                    'total' => $total,
                    'scaled' => $group->where('ads_status', 'scale')->count(),
                    'approval_rate' => $total > 0 ? round($approved / $total * 100) : 0,
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(
        Workspace $workspace,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        ?int $creatorId
    ): array {
        return CreativeReview::query()
            ->whereHas('creative', function (Builder $q) use ($workspace, $dateFrom, $dateTo, $creatorId) {
                $q->where('workspace_id', $workspace->id)
                    ->whereBetween('creative_date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
                    ->when($creatorId, fn (Builder $qq) => $qq->where('creator_id', $creatorId));
            })
            ->with(['reviewer:id,name', 'creative:id,name'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (CreativeReview $r) => [
                'id' => $r->id,
                'creative_id' => $r->creative_id,
                'creative_name' => optional($r->creative)->name,
                'status' => $r->status,
                'feedback' => $r->feedback,
                'reviewer' => optional($r->reviewer)->name,
                'created_at' => $r->created_at?->format('M j, Y g:i A'),
            ])
            ->values()
            ->all();
    }

    // ─── Status derivation ─────────────────────────────────────────────────

    private function isNeedsRevision(Creative $c): bool
    {
        return $c->final_status === 'for_revision'
            || optional($c->latestReview)->status === 'revision';
    }

    private function isAwaitingReview(Creative $c): bool
    {
        return $c->final_status !== 'approved'
            && in_array(optional($c->latestReview)->status, ['for_approval', 'for_reapproval'], true);
    }

    private function isWaiting(Creative $c): bool
    {
        if ($c->final_status === 'approved') {
            return false;
        }

        $status = optional($c->latestReview)->status;

        return $status === null || $status === 'waiting_for_submission';
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
            'product' => optional($c->product)->title,
            'final_status' => $c->final_status,
            'feedback' => optional($c->latestReview)->feedback,
            'reviewer' => optional(optional($c->latestReview)->reviewer)->name,
        ];
    }
}
