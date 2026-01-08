<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\OptimizationRule;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdsManagerController extends Controller
{
    /**
     * Display the ads manager page with optimization rules.
     */
    public function index(Workspace $workspace, Request $request)
    {
        $query = QueryBuilder::for(OptimizationRule::class)
            ->where('workspace_id', $workspace->id)
            ->allowedFilters([
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts([
                'name',
                'description',
                'target',
                'action',
                'status',
                'created_at',
                'updated_at',
            ])
            ->defaultSort('-created_at');

        // Apply date range filter if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->get('start_date') . ' 00:00:00',
                $request->get('end_date') . ' 23:59:59'
            ]);
        }

        $rules = $query->paginate($request->get('perPage', 20))
            ->withQueryString();

        return Inertia::render('workspaces/ads-manager/index', [
            'workspace' => $workspace,
            'rules' => $rules,
            'query' => [
                'sort' => $request->get('sort'),
                'perPage' => $request->get('perPage', 20),
                'page' => $request->get('page', 1),
                'filter' => [
                    'search' => $request->get('filter.search'),
                    'status' => $request->get('filter.status'),
                ],
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
            ],
        ]);
    }
}
