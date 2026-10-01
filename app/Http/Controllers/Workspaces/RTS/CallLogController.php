<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Workspace;
use App\Support\CallLogCallers;
use App\Support\CallLogPersona;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
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
 * join that names them all. That is also why "User" is not a sortable column.
 */
class CallLogController extends Controller
{
    use AuthorizesRequests;

    /** Personas a row can be filtered to, plus the pseudo-value for an unmatched call. */
    private const UNMATCHED = 'unmatched';

    public function index(Workspace $workspace, Request $request)
    {
        $this->authorize(Permission::ViewCallLogs->value, $workspace);

        $user = $request->user();

        // Resolved once: shouldScope() reads the "viewing as team" choice, which
        // also writes it back to the session, and the persona list below asks
        // the same question again.
        $scoped = TeamVisibility::shouldScope($user, $workspace);

        $calls = CallLog::where('call_logs.workspace_id', $workspace->id)
            // A call reaches a team only through the order it matched, so team
            // scoping rides on the order relation the way the parcel-update list
            // does. An unmatched call belongs to no order and so to no team:
            // whereHas() drops it for a scoped viewer along with the calls on
            // other teams' orders, which is the fail-closed reading the rest of
            // the app takes. Unrestricted viewers still see the whole register.
            ->when(
                $scoped,
                fn ($query) => $query->whereHas('order', fn ($order) => $order->visibleTo($user, $workspace)),
            );

        $logs = QueryBuilder::for($calls)
            ->allowedFilters([
                // Phone number or order id — the two things someone arrives at
                // this page holding, and the two the table itself shows. The id
                // is matched whole: it is the order's primary key, so a `like`
                // on a short run of digits would answer with unrelated orders.
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('phone_number', 'like', "%{$value}%")
                            // Through the relation, not call_logs.order_id: a call
                            // can point at an order that has since gone from the
                            // synced table, and the Order column shows those as
                            // unmatched, so searching must not find them either.
                            ->orWhereHas('order', fn ($o) => $o->whereKey($value));
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
            ->allowedSorts(['call_date', 'duration', 'persona', 'type', 'phone_number'])
            // call_time is a time, not a timestamp, so the day has to lead the sort;
            // id breaks ties within a second so paging can't repeat or skip a row.
            ->defaultSort('-call_date')
            ->orderByDesc('call_time')
            ->orderByDesc('id')
            ->with('order:id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $logs->setCollection(
            CallLogCallers::stamp($logs->getCollection())->map(fn (CallLog $log) => [
                ...$log->only([
                    'id', 'phone_number', 'type', 'duration', 'call_date', 'call_time', 'persona', 'called_by',
                ]),
                // Read off the relation rather than the column: a call can carry an
                // order_id whose row has since gone from the synced table, and an
                // id with nothing behind it is not something to link to.
                'order_id' => $log->order?->id,
            ])
        );

        return Inertia::render('workspaces/rts/call-logs', [
            'workspace' => $workspace,
            'logs' => $logs,
            // "Unmatched" is dropped for a scoped viewer rather than left as a
            // filter that can only ever come back empty: persona and order id
            // are stamped together, so the rows it selects are exactly the ones
            // the team scope above has already removed.
            'personas' => array_values(array_filter([
                CallLogPersona::CUSTOMER,
                CallLogPersona::RIDER,
                CallLogPersona::VERIFICATION,
                $scoped ? null : self::UNMATCHED,
            ])),
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
