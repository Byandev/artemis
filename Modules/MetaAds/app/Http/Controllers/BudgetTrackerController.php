<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PageDailyBudgetRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Products\Models\Product;

class BudgetTrackerController extends Controller
{
    /**
     * Ad Spent Budget Tracker — page daily budgets rolled up per page,
     * per product and per user, comparing today against yesterday.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Only two days of page budgets for one workspace — small enough to
        // pull and aggregate in PHP rather than three separate group-by queries.
        $user = $request->user();

        $records = PageDailyBudgetRecord::query()
            ->where('workspace_id', $workspace->id)
            ->when(
                TeamVisibility::shouldScope($user, $workspace),
                fn ($q) => $q->whereHas('page', fn ($p) => $p->visibleTo($user, $workspace)),
            )
            ->whereIn('date', [$today, $yesterday])
            ->with(['page' => fn ($q) => $q->withTrashed()->select('id', 'name', 'shop_id', 'owner_id')
                ->with(['shop' => fn ($s) => $s->select('id', 'product_id')])])
            ->get(['id', 'page_id', 'date', 'budget']);

        // Resolve labels once for the product and user groupings.
        $productNames = Product::where('workspace_id', $workspace->id)->pluck('name', 'id');
        $userNames = User::whereIn('id', $records->pluck('page.owner_id')->filter()->unique())
            ->pluck('name', 'id');

        $perPage = $this->aggregate(
            $records,
            $today,
            $yesterday,
            fn ($rec) => $rec->page?->id,
            fn ($rec) => $rec->page?->name ?: 'Untitled page',
        );

        $perProduct = $this->aggregate(
            $records,
            $today,
            $yesterday,
            fn ($rec) => $rec->page?->shop?->product_id,
            fn ($rec) => $rec->page?->shop?->product_id
                ? ($productNames[$rec->page->shop->product_id] ?? 'Unknown product')
                : 'Unassigned',
        );

        $perUser = $this->aggregate(
            $records,
            $today,
            $yesterday,
            fn ($rec) => $rec->page?->owner_id,
            fn ($rec) => $rec->page?->owner_id
                ? ($userNames[$rec->page->owner_id] ?? 'Unknown user')
                : 'Unassigned',
        );

        return Inertia::render('workspaces/integrations/meta-budget-tracker', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'dates' => ['today' => $today, 'yesterday' => $yesterday],
            'perPage' => $perPage,
            'perProduct' => $perProduct,
            'perUser' => $perUser,
        ]);
    }

    /**
     * Bucket the budget records by the given key, summing today's and
     * yesterday's budget into one row per bucket.
     */
    private function aggregate(
        Collection $records,
        string $today,
        string $yesterday,
        callable $keyFn,
        callable $labelFn,
    ): Collection {
        $buckets = [];

        foreach ($records as $rec) {
            $key = $keyFn($rec);
            $bucketKey = $key === null ? '__none__' : (string) $key;

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'id' => $key === null ? 'none' : (string) $key,
                    'name' => $labelFn($rec),
                    'today' => 0.0,
                    'yesterday' => 0.0,
                ];
            }

            $date = $rec->date instanceof Carbon
                ? $rec->date->toDateString()
                : (string) $rec->date;

            if ($date === $today) {
                $buckets[$bucketKey]['today'] += (float) $rec->budget;
            } elseif ($date === $yesterday) {
                $buckets[$bucketKey]['yesterday'] += (float) $rec->budget;
            }
        }

        return collect($buckets)
            ->map(fn ($b) => [
                'id' => $b['id'],
                'name' => $b['name'],
                'today' => round($b['today'], 2),
                'yesterday' => round($b['yesterday'], 2),
                'difference' => round($b['today'] - $b['yesterday'], 2),
            ])
            ->sortByDesc('today')
            ->values();
    }
}
