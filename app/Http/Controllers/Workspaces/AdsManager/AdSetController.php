<?php

namespace App\Http\Controllers\Workspaces\AdsManager;

use App\Http\Controllers\Controller;
use App\Models\AdSet;
use App\Models\Workspace;
use Illuminate\Http\Request;

class AdSetController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $adSets = AdSet::query()
            ->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->with(['campaign', 'adAccount'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            });

        // Aggregate metrics from ad_records for the given date range
        if ($startDate && $endDate) {
            $adSets->addSelect('ad_sets.*')
                ->selectRaw(
                    '(SELECT COALESCE(SUM(impressions), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id AND ad_records.date BETWEEN ? AND ?) as impressions',
                    [$startDate, $endDate]
                )
                ->selectRaw(
                    '(SELECT COALESCE(SUM(clicks), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id AND ad_records.date BETWEEN ? AND ?) as clicks',
                    [$startDate, $endDate]
                )
                ->selectRaw(
                    '(SELECT COALESCE(SUM(spend), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id AND ad_records.date BETWEEN ? AND ?) as spend',
                    [$startDate, $endDate]
                );
        }

        return inertia('workspaces/ads-manager/ad-sets', [
            'workspace' => $workspace,
            'adSets' => $adSets
                ->orderBy('created_at', 'desc')
                ->paginate(10),
            'query' => [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'page' => $request->get('page', 1),
            ],
        ]);
    }
}
