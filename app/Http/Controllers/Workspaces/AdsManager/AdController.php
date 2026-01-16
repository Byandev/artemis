<?php

namespace App\Http\Controllers\Workspaces\AdsManager;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        $ads = Ad::query()
            ->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->with(['campaign', 'adSet', 'adAccount'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->filters, function ($query) use ($request) {
                $filters = json_decode($request->filters, true);
                if (is_array($filters)) {
                    foreach ($filters as $filter) {
                        $metric = $filter['metric'] ?? null;
                        $operator = $filter['operator'] ?? null;
                        $value = $filter['value'] ?? null;

                        if ($metric && $operator && $value !== null) {
                            // Map operator to SQL operator
                            $sqlOperator = match ($operator) {
                                'gt' => '>',
                                'gte' => '>=',
                                'lt' => '<',
                                'lte' => '<=',
                                'eq' => '=',
                                'neq' => '!=',
                                default => '='
                            };

                            $query->where($metric, $sqlOperator, $value);
                        }
                    }
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('workspaces/ads-manager/ads', [
            'workspace' => $workspace,
            'ads' => $ads,
            'filters' => $request->only(['search', 'status', 'filters']),
        ]);
    }
}
