<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::with(['causer', 'subject', 'workspace']);

        // Workspace filter
        if ($request->filled('workspace')) {
            $query->where('workspace_id', $request->input('workspace'));
        }

        // Event filter
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        // Causer filter - search by user name or email
        if ($request->filled('causer')) {
            $causerSearch = $request->input('causer');
            $query->whereHas('causer', function ($q) use ($causerSearch) {
                $q->where('name', 'like', '%'.$causerSearch.'%')
                    ->orWhere('email', 'like', '%'.$causerSearch.'%');
            });
        }

        // Date range filters
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $sortInput = (string) $request->input('sort', '-created_at');
        $sortDirection = str_starts_with($sortInput, '-') ? 'desc' : 'asc';
        $sortColumn = ltrim($sortInput, '-');

        match ($sortColumn) {
            'workspace' => $query->orderBy(
                Workspace::select('name')
                    ->whereColumn('workspaces.id', 'activity_log.workspace_id')
                    ->limit(1),
                $sortDirection
            ),
            'causer' => $query->orderBy(
                User::select('name')
                    ->whereColumn('users.id', 'activity_log.causer_id')
                    ->where('activity_log.causer_type', User::class)
                    ->limit(1),
                $sortDirection
            ),
            'event' => $query->orderBy('event', $sortDirection),
            'subject' => $query->orderBy('subject_type', $sortDirection),
            'description' => $query->orderBy('description', $sortDirection),
            default => $query->orderBy('created_at', $sortDirection),
        };

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 25, 50, 100, 500], true) ? $perPage : 20;

        $activities = $query->paginate($perPage)->withQueryString()->through(function ($act) {
            return [
                'id' => $act->id,
                'event' => $act->event,
                'workspace_id' => $act->workspace_id,
                'workspace' => $act->workspace ? ['id' => $act->workspace->id, 'name' => $act->workspace->name] : null,
                'description' => $act->description,
                'properties' => $act->properties,
                'causer' => $act->causer ? ['id' => $act->causer->id, 'name' => $act->causer->name] : null,
                'subject' => $act->subject ? ['id' => $act->subject->id ?? null, 'type' => class_basename($act->subject)] : null,
                'created_at' => $act->created_at,
            ];
        });

        $workspaces = Workspace::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('admin/activity-log/index', [
            'activities' => $activities,
            'workspaces' => $workspaces,
            'filters' => [
                'workspace' => $request->input('workspace', ''),
                'event' => $request->input('event', ''),
                'causer' => $request->input('causer', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'per_page' => $perPage,
                'sort' => $sortColumn,
                'direction' => $sortDirection,
            ],
        ]);
    }
}
