<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::with(['causer', 'subject', 'workspace'])->orderByDesc('created_at');

        // Workspace filter
        if ($request->filled('workspace')) {
            $query->where('workspace_id', $request->input('workspace'));
        }

        // Event filter
        if ($request->filled('event')) {
            $query->where('description', 'like', '%'.$request->input('event').'%');
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

        $activities = $query->paginate(20)->through(function ($act) {
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
            ],
        ]);
    }
}
