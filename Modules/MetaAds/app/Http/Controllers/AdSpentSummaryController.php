<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdSpentSummaryController extends Controller
{
    /**
     * Ad Spent ROAS Summary — one row per day over the selected range with the
     * workspace's total orders, sales, ad spend and ROAS (sales ÷ ad spend),
     * summed across advertisers from advertiser_performance_daily_records.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        // Default to the trailing 7 days (inclusive).
        $end = ($request->date('end_date') ?? now())->startOfDay();
        $start = ($request->date('start_date') ?? now()->copy()->subDays(6))->startOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        // Gencys partners read the Gencys pipeline's rows (advertiser = Intern);
        // everyone else reads the Artemis rows (advertiser = User).
        $source = $workspace->is_gencys_partner
            ? AdvertiserPerformanceDailyRecord::SOURCE_GENCYS
            : AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS;

        $daily = AdvertiserPerformanceDailyRecord::query()
            ->where('workspace_id', $workspace->id)
            ->where('source', $source)
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->get([
                'date',
                DB::raw('COALESCE(SUM(orders), 0) as orders'),
                DB::raw('COALESCE(SUM(sales), 0) as sales'),
                DB::raw('COALESCE(SUM(ad_spent), 0) as ad_spent'),
            ])
            ->keyBy(fn ($r) => $r->date->toDateString());

        // One row per calendar day so days without records still render.
        $rows = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $day = $cursor->toDateString();
            $record = $daily[$day] ?? null;
            $sales = (float) ($record->sales ?? 0);
            $adSpent = (float) ($record->ad_spent ?? 0);

            $rows[] = [
                'date' => $day,
                'orders' => (int) ($record->orders ?? 0),
                'sales' => round($sales, 2),
                'ad_spent' => round($adSpent, 2),
                'roas' => $adSpent > 0 ? round($sales / $adSpent, 2) : null,
            ];
        }

        return Inertia::render('workspaces/integrations/meta-ad-spent-summary', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'rows' => $rows,
        ]);
    }
}
