<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Workspace;
use App\Support\CallLogCallers;
use App\Support\CallLogPersona;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The workspace's call register.
 *
 * The same rows the RMO call-log modal shows one number at a time, listed whole:
 * every call the mobile app has synced for this workspace, newest first, with the
 * persona and order that App\Support\CallLogPersona matched them to.
 *
 * Callers are named a page at a time rather than joined — `user_id` and
 * `assignee_user_id` point into two different tables (Pancake users and system
 * users) depending on which build of the app sent the row, so there is no single
 * join that names them all. Sorting by "User" therefore orders on a pair of
 * subqueries rather than a joined column; see the `called_by` sort below.
 */
class CallLogController extends Controller
{
    use AuthorizesRequests;

    /** Personas a row can be filtered to, plus the pseudo-value for an unmatched call. */
    private const UNMATCHED = 'unmatched';

    public function index(Workspace $workspace, Request $request)
    {
        $this->authorize(Permission::ViewCallLogs->value, $workspace);

        $logs = QueryBuilder::for(CallLog::where('call_logs.workspace_id', $workspace->id))
            ->allowedFilters([
                // Phone number or order number — the two things someone arrives
                // at this page holding.
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('phone_number', 'like', "%{$value}%")
                            ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$value}%"));
                    });
                }),
                AllowedFilter::callback('start_date', fn ($query, $value) => $query->whereDate('call_date', '>=', $value)),
                AllowedFilter::callback('end_date', fn ($query, $value) => $query->whereDate('call_date', '<=', $value)),
                // "Unmatched" is a real answer, not a missing one: the number was
                // on no delivery and no order confirmed that day.
                AllowedFilter::callback('persona', fn ($query, $value) => $value === self::UNMATCHED
                    ? $query->whereNull('persona')
                    : $query->where('persona', $value)),
                AllowedFilter::exact('type'),
            ])
            ->allowedSorts([
                'call_date',
                'duration',
                'persona',
                'type',
                'phone_number',
                // The caller's name lives in whichever of the two user tables the
                // syncing app knew about, so it is looked up per row rather than
                // joined: a join would need both tables, and both carry `id` and
                // `name` columns that would collide with the ones already being
                // selected and ordered on here.
                AllowedSort::callback('called_by', function ($query, bool $descending) {
                    // COALESCE in the same order CallLogCallers resolves the name,
                    // so the column sorts on the name it actually shows.
                    $query->orderByRaw(
                        'COALESCE('
                        .'(select name from users where users.id = call_logs.assignee_user_id), '
                        .'(select name from pancake_users where pancake_users.id = call_logs.user_id)'
                        .') '.($descending ? 'desc' : 'asc')
                    );
                }),
            ])
            // call_time is a time, not a timestamp, so the day has to lead the sort;
            // id breaks ties within a second so paging can't repeat or skip a row.
            ->defaultSort('-call_date')
            ->orderByDesc('call_logs.call_time')
            ->orderByDesc('call_logs.id')
            ->with('order:id,order_number')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $logs->setCollection(
            CallLogCallers::stamp($logs->getCollection())->map(fn (CallLog $log) => [
                ...$log->only([
                    'id', 'phone_number', 'type', 'duration', 'call_date', 'call_time', 'persona', 'called_by',
                ]),
                // Read off the relation rather than the column: a call can carry an
                // order_id whose row has since gone from the synced table, and a
                // number with nothing behind it is not something to link to.
                'order_number' => $log->order?->order_number,
            ])
        );

        return Inertia::render('workspaces/rts/call-logs', [
            'workspace' => $workspace,
            'logs' => $logs,
            'personas' => [
                CallLogPersona::CUSTOMER,
                CallLogPersona::RIDER,
                CallLogPersona::VERIFICATION,
                self::UNMATCHED,
            ],
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
