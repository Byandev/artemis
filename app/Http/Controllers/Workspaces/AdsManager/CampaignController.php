<?php

namespace App\Http\Controllers\Workspaces\AdsManager;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $campaigns = Campaign::query()
            ->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->with(['adAccount'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            });

        // Aggregate metrics from ad_records for the given date range
        if ($startDate && $endDate) {
            $campaigns->addSelect('campaigns.*')
                ->selectRaw(
                    '(SELECT COALESCE(SUM(impressions), 0) FROM ad_records WHERE ad_records.campaign_id = campaigns.id AND ad_records.date BETWEEN ? AND ?) as impressions',
                    [$startDate, $endDate]
                )
                ->selectRaw(
                    '(SELECT COALESCE(SUM(clicks), 0) FROM ad_records WHERE ad_records.campaign_id = campaigns.id AND ad_records.date BETWEEN ? AND ?) as clicks',
                    [$startDate, $endDate]
                )
                ->selectRaw(
                    '(SELECT COALESCE(SUM(spend), 0) FROM ad_records WHERE ad_records.campaign_id = campaigns.id AND ad_records.date BETWEEN ? AND ?) as spend',
                    [$startDate, $endDate]
                );
        }

        return response()->json(
            $campaigns
                ->orderBy('created_at', 'desc')
                ->paginate(10)
        );
    }
}
