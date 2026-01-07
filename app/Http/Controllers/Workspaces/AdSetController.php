<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\AdSet;
use App\Models\Workspace;
use Illuminate\Http\Request;

class AdSetController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        $adSets = AdSet::query()
            ->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->with(['campaign', 'adAccount'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
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
                            $sqlOperator = match($operator) {
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

        return response()->json($adSets);
    }
}
