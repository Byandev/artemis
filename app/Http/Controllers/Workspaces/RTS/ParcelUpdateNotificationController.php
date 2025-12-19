<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Http\Sorts\ParcelJourneyNotification\OrderNumberSort;
use App\Http\Sorts\ParcelJourneyNotification\PageNameSort;
use App\Models\ParcelJourneyNotification;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ParcelUpdateNotificationController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        // Prepare lists for filters
        $baseResults = ParcelJourneyNotification::query()
            ->whereHas('order', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->with(['order.page'])
            ->get();

        $pages = $baseResults->pluck('order.page.name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $types = $baseResults->pluck('type')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Build filtered and sorted query using QueryBuilder
        $notifications = QueryBuilder::for(
            ParcelJourneyNotification::query()
                ->whereHas('order', function ($query) use ($workspace) {
                    $query->where('workspace_id', $workspace->id);
                })
        )
            ->with(['order.page'])
            ->allowedFilters([
                AllowedFilter::scope('page_name', 'filterByPageName'),
                AllowedFilter::exact('type'),
            ])
            ->allowedSorts([
                'type',
                'message',
                'created_at',
                AllowedSort::custom('order.order_number', new OrderNumberSort),
                AllowedSort::custom('order.page.name', new PageNameSort),
            ])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/rts/parcel-update-notification', [
            'workspace' => $workspace,
            'notifications' => $notifications,
            'pages' => $pages,
            'types' => $types,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
