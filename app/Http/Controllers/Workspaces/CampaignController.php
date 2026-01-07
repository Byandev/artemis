<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Workspace;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
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
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('start_time', [$request->start_date, $request->end_date]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($campaigns);
    }
}
